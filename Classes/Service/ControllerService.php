<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\Event\CustomDetailEntitySourceEvent;
use Remind\Extbase\Event\EnrichDetailResultEvent;
use Remind\Extbase\Event\ModifyFilterableListResultEvent;
use Remind\Extbase\FlexForms\DetailDataSheets;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\SelectionDataSheets;
use Remind\Extbase\Service\Dto\DetailResult;
use Remind\Extbase\Service\Dto\FilterableListResult;
use Remind\Extbase\Service\Dto\FilterValue;
use Remind\Extbase\Service\Dto\FrontendFilter;
use Remind\Extbase\Service\Dto\ListResult;
use Remind\Extbase\Utility\Dto\Conjunction;
use Remind\Extbase\Utility\Dto\DatabaseFilter;
use Remind\Extbase\Utility\FilterUtility;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\RequestBuilder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ControllerService
{
    private array $settings;
    private string $extensionName;
    private string $pluginName;
    private string $filterTable;
    private string $filtersArgumentName;
    private Request $request;
    private FilterableRepository $repository;
    private ContentObjectRenderer $cObj;

    public function __construct(
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly UriBuilder $uriBuilder,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DatabaseService $databaseService,
        ConfigurationManagerInterface $configurationManager,
        RequestBuilder $requestBuilder,
    ) {
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
        // TODO: replace deprecated function call
        $this->cObj = $configurationManager->getContentObject();
        $this->settings = $configuration['settings'] ?? [];
        $this->extensionName = $configuration['extensionName'];
        $this->pluginName = $configuration['pluginName'];
        $this->request = $requestBuilder->build($this->getRequest());
        $this->uriBuilder->setRequest($this->request);
        $this->filterTable = PluginUtility::getTableName($this->extensionName . '_' . $this->pluginName);
    }

    public function getFilterableList(
        FilterableRepository $repository,
        int $currentPage,
        ?array $filters = null,
        ?string $filtersArgumentName = 'filter',
    ): FilterableListResult {
        $this->repository = $repository;
        $this->filtersArgumentName = $filtersArgumentName;
        $queryDatabaseFilters = $this->getQueryDatabaseFilters($filters);
        $appliedDatabaseFilters = FilterUtility::getAppliedValuesDatabaseFilters(
            $this->settings,
            $this->filterTable
        );
        $databaseFilters = array_merge(
            array_values($appliedDatabaseFilters),
            array_values($queryDatabaseFilters)
        );
        $listResult = $this->getListResult($currentPage, $databaseFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters($appliedDatabaseFilters, $queryDatabaseFilters);
        $filterableListResult->setFrontendFilters($frontendFilters);
        /** @var ModifyFilterableListResultEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyFilterableListResultEvent($filterableListResult));
        $filterableListResult = $event->getFilterableListResult();
        $this->addCacheTag($this->filterTable);
        return $filterableListResult;
    }

    public function getSelectionList(FilterableRepository $repository, int $currentPage): ListResult
    {
        $this->repository = $repository;
        $recordUids = $this->settings[SelectionDataSheets::RECORDS];
        $recordUids = GeneralUtility::intExplode(',', $recordUids, true);

        if (empty($recordUids)) {
            $result = new ListResult();
            $result->setPaginatedItems([]);
            return $result;
        }

        $filters = [
            new DatabaseFilter('uid', array_map(function (int $uid) {
                return ['uid' => $uid];
            }, $recordUids), false, Conjunction::OR),
        ];
        $this->addCacheTag($this->filterTable);
        return $this->getListResult($currentPage, $filters);
    }

    public function getDetailResult(
        RepositoryInterface $repository,
        ?AbstractEntity $entity
    ): ?DetailResult {
        $source = $this->settings[DetailDataSheets::SOURCE];
        $result = new DetailResult();
        $item = null;
        switch ($source) {
            case DetailDataSheets::SOURCE_DEFAULT:
                $item = $entity;
                break;
            case DetailDataSheets::SOURCE_RECORD:
                $uid = (int) ($this->settings[DetailDataSheets::RECORD] ?? null);
                $item = $repository->findByUid($uid);
                break;
            default:
                /** @var \TYPO3\CMS\Core\Routing\PageArguments $routing */
                $routing = $this->request->getAttribute('routing');
                $arguments = $routing->getArguments();

                /** @var CustomDetailEntitySourceEvent $event */
                $event = $this->eventDispatcher->dispatch(
                    new CustomDetailEntitySourceEvent($this->extensionName, $source, $arguments)
                );
                $item = $event->getResult();
                break;
        }
        $result->setItem($item);

        $properties = json_decode($this->settings[DetailDataSheets::PROPERTIES], true);
        $properties = array_map(function (array $property) {
            return [
                ...$property,
                'value' => GeneralUtility::underscoredToLowerCamelCase($property['value']),
            ];
        }, $properties);
        $result->setProperties($properties);

        /** @var EnrichDetailResultEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new EnrichDetailResultEvent($result)
        );

        $result = $event->getDetailResult();

        if ($item) {
            $this->addCacheTag($this->filterTable . '_' . $item->getUid());
        }
        return $result;
    }

    public function addCacheTag(string $cacheTag)
    {
        $typoScriptFrontendController = $this->getTypoScriptFrontendController();
        if ($typoScriptFrontendController) {
            $typoScriptFrontendController->addCacheTags([$cacheTag]);
        }
    }


    /**
     * @param int $currentPage
     * @param DatabaseFilter[] $filters
     */
    private function getListResult(int $currentPage, ?array $filters = []): ListResult
    {
        $result = new ListResult();
        $limit = (int) ($this->settings[ListSheets::LIMIT] ?? null);
        $orderBy = $this->settings[ListSheets::ORDER_BY] ?? null;
        $orderDirection = $this->settings[ListSheets::ORDER_DIRECTION] ?? null;
        $itemsPerPage = (int) ($this->settings[ListSheets::ITEMS_PER_PAGE] ?? null);

        $queryResult = $this->repository->findByFilters(
            $filters,
            $limit,
            $orderBy,
            $orderDirection
        );
        $result->setPaginatedItems($queryResult);
        $result->setCount($queryResult->count());

        if ($limit) {
            $queryWithoutLimit = $queryResult->getQuery();
            if ($queryWithoutLimit instanceof Query) {
                // leads to Warning:
                // Undefined property: TYPO3\CMS\Extbase\Persistence\Generic\Query::$limit
                $queryWithoutLimit->unsetLimit();
                $result->setCountWithoutLimit($queryWithoutLimit->count());
            }
        }

        if (!$result->getCountWithoutLimit()) {
            $result->setCountWithoutLimit($result->getCount());
        }

        if ($itemsPerPage) {
            $paginator = new QueryResultPaginator($queryResult, $currentPage, $itemsPerPage);
            $pagination = new SimplePagination($paginator);
            $result->setPagination($pagination);
            $result->setPaginatedItems($paginator->getPaginatedItems());
        }

        return $result;
    }

    /**
     * @param DatabaseFilter[] $appliedDatabaseFilters
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @return FrontendFilter[]
     */
    private function getFrontendFilters(array $appliedDatabaseFilters, array $queryDatabaseFilters): array
    {
        $result = [];
        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER] ?? [];
            $filterName = FilterUtility::getFilterName($filterSetting);

            $disabled = (bool) ($filterSetting[ListFiltersSheets::DISABLED] ?? false);

            if ($disabled) {
                continue;
            }

            $filterValues = json_decode($filterSetting[ListFiltersSheets::AVAILABLE_VALUES] ?? '', true);

            $dynamicValues = (bool) ($filterSetting[ListFiltersSheets::DYNAMIC_AVAILABLE_VALUES] ?? false);

            if ($dynamicValues) {
                $fieldNames = GeneralUtility::trimExplode(',', $filterName, true);
                $dynamicFilterValues = $this->databaseService->getAvailableFieldValues(
                    $this->filterTable,
                    $fieldNames,
                    $this->cObj->data['pages'],
                    $this->cObj->data['recursive'],
                    $appliedDatabaseFilters,
                );

                foreach ($filterValues as $filterValue) {
                    foreach ($dynamicFilterValues as &$dynamicFilterValue) {
                        if ($dynamicFilterValue['value'] === $filterValue['value']) {
                            $dynamicFilterValue = $filterValue;
                            break;
                        }
                    }
                }

                $filterValues = $dynamicFilterValues;
            }

            if (empty($filterValues)) {
                continue;
            }

            $exclusive = (bool) $filterSetting[ListFiltersSheets::EXCLUSIVE];

            $label = $this->getFrontendFilterLabel($filterName, $filterSetting);

            $allValuesLabel = $filterSetting[ListFiltersSheets::ALL_VALUES_LABEL] ?? '';

            $allValues = new FilterValue([], $allValuesLabel);
            $allValues->setLink($this->getFrontendFilterLink($filterName, $queryDatabaseFilters, [], $exclusive));

            $frontendFilter = new FrontendFilter(
                $filterName,
                $label,
                $allValues,
                $filterSetting[ListFiltersSheets::VALUE_PREFIX] ?? '',
                $filterSetting[ListFiltersSheets::VALUE_SUFFIX] ?? '',
            );

            foreach ($filterValues as $filterValue) {
                $label = $filterValue['label'];
                $value = json_decode($filterValue['value'], true);
                $frontendFilter->addValue(new FilterValue($value, $label));
            }

            foreach ($frontendFilter->getValues() as $filterValue) {
                $value = $filterValue->getValue();

                $active = $this->getFrontendFilterActive(
                    $filterName,
                    $queryDatabaseFilters,
                    $value
                );
                $filterValue->setActive($active);

                $link = $this->getFrontendFilterLink(
                    $filterName,
                    $queryDatabaseFilters,
                    $value,
                    $exclusive
                );
                $filterValue->setLink($link);

                $count = $this->getFrontendFilterCount(
                    $filterSetting,
                    $filterName,
                    $appliedDatabaseFilters,
                    $queryDatabaseFilters,
                    $value,
                    $exclusive
                );
                $filterValue->setCount($count);
            }

            $result[] = $frontendFilter;
        }
        return $result;
    }

    /**
     * @param string $filterName
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param array $value
     * @return bool
     */
    private function getFrontendFilterActive(
        string $filterName,
        array $queryDatabaseFilters,
        array $value,
    ): bool {
        $databaseFilter = $queryDatabaseFilters[$filterName] ?? null;

        if ($databaseFilter) {
            $databaseFilterValues = $databaseFilter->getValues();
            return count(
                array_filter($databaseFilterValues, function (array $databaseFilterValue) use ($value) {
                    return $databaseFilterValue == $value;
                })
            ) > 0;
        }

        return false;
    }

    private function getFrontendFilterLabel(string $filterName, array $filterSetting): string
    {
        $label = $filterSetting[ListFiltersSheets::LABEL];
        if (!$label) {
            $fields = GeneralUtility::trimExplode(',', $filterName, true);
            $labels = array_map(function (string $field) {
                $label = BackendUtility::getItemLabel($this->filterTable, $field);
                if (str_starts_with($label, 'LLL:')) {
                    $label = LocalizationUtility::translate($label);
                }
                return $label;
            }, $fields);
            $label = implode(', ', $labels);
        }
        return $label;
    }

    /**
     * @param string $filterName
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param array $values
     * @param bool $exclusive
     * @return string
     */
    private function getFrontendFilterLink(
        string $filterName,
        array $queryDatabaseFilters,
        array $values,
        bool $exclusive,
    ): string {
        $filterArguments = [];

        $filters = array_map(function (DatabaseFilter $databaseFilter) {
            return $databaseFilter->getValues();
        }, $queryDatabaseFilters);

        $index = array_search($values, $filters[$filterName] ?? []);
        if ($index !== false) {
            // remove argument if it is already active so the link removes the filter
            array_splice($filters[$filterName], $index, 1);
        } else {
            if ($exclusive) {
                $filters[$filterName] = [$values];
            } else {
                $filters[$filterName][] = $values;
            }
        }
        $filters = array_filter($filters);

        array_walk_recursive($filters, function (mixed $value, string $key) use (&$filterArguments) {
            $filterArguments[$key][] = $value;
        });

        $filterArguments = FilterUtility::simplifyQueryParameters($filterArguments);

        return $this->uriBuilder
            ->reset()
            ->uriFor(null, [$this->filtersArgumentName => $filterArguments]);
    }

    /**
     * @param DatabaseFilter[] $appliedDatabaseFilters
     * @param DatabaseFilter[] $queryDatabaseFilters
     */
    private function getFrontendFilterCount(
        array $filterSetting,
        string $filterName,
        array $appliedDatabaseFilters,
        array $queryDatabaseFilters,
        array $value,
        bool $exclusive
    ): int {
        $tmpFilters = $queryDatabaseFilters;

        $databaseFilter = isset($tmpFilters[$filterName])
            ? clone ($tmpFilters[$filterName])
            : FilterUtility::getDatabaseFilter($filterSetting, $filterName, [], $this->filterTable);

        $tmpFilters[$filterName] = $databaseFilter;

        $index = array_search($value, $databaseFilter->getValues());
        if ($index === false) {
            if ($exclusive) {
                $databaseFilter->setValues([]);
            }
            $databaseFilter->addValue($value);
        } else {
            $databaseFilterValues = $databaseFilter->getValues();
            array_splice($databaseFilterValues, $index, 1);
            $databaseFilter->setValues($databaseFilterValues);
        }

        $databaseFilters = array_merge(
            array_values($appliedDatabaseFilters),
            array_values($tmpFilters)
        );

        $queryResult = $this->repository->findByFilters($databaseFilters);
        return $queryResult->count();
    }

    /**
     * @return DatabaseFilter[]
     */
    private function getQueryDatabaseFilters(array $filters): array
    {
        $result = [];
        $filters = FilterUtility::normalizeQueryParameters($filters);

        foreach ($filters as $fieldName => &$values) {
            foreach ($values as &$value) {
                $value = [$fieldName => $value];
            }
        }

        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $filterName = FilterUtility::getFilterName($filterSetting);
            $allowMultipleFields = (bool) $filterSetting[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
            if ($allowMultipleFields) {
                $fields = GeneralUtility::trimExplode(',', $filterName, true);
                foreach ($fields as $field) {
                    if (isset($filters[$field])) {
                        if (!isset($filters[$filterName])) {
                            $filters[$filterName] = [];
                        }
                        ArrayUtility::mergeRecursiveWithOverrule($filters[$filterName], $filters[$field]);
                        unset($filters[$field]);
                    }
                }
            }
            if (isset($filters[$filterName])) {
                $result[$filterName] = FilterUtility::getDatabaseFilter(
                    $filterSetting,
                    $filterName,
                    $filters[$filterName],
                    $this->filterTable
                );
            }
        }

        return $result;
    }

    private function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'] ?? null;
    }

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
