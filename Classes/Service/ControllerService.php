<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\Event\EnrichDetailResultEvent;
use Remind\Extbase\Event\ModifyDetailItemEvent;
use Remind\Extbase\Event\ModifyDetailPageTitleEvent;
use Remind\Extbase\Event\ModifyFilterableListResultEvent;
use Remind\Extbase\FlexForms\DetailSheets;
use Remind\Extbase\FlexForms\FrontendFilterSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\SelectionSheets;
use Remind\Extbase\PageTitle\ExtbasePageTitleProvider;
use Remind\Extbase\Service\Dto\DetailResult;
use Remind\Extbase\Service\Dto\FilterableListResult;
use Remind\Extbase\Service\Dto\FilterValue;
use Remind\Extbase\Service\Dto\FrontendFilter;
use Remind\Extbase\Service\Dto\ListResult;
use Remind\Extbase\Utility\ControllerUtility;
use Remind\Extbase\Utility\Dto\Conjunction;
use Remind\Extbase\Utility\Dto\DatabaseFilter;
use Remind\Extbase\Utility\FilterUtility;
use Remind\Extbase\Utility\PluginUtility;
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
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ControllerService
{
    /**
     * @var mixed[]
     */
    private array $settings;

    private string $extensionName;

    private string $pluginName;

    private string $tableName;

    private string $filtersArgumentName;

    private bool $disableFilterCount;

    /** @var \Remind\Extbase\Service\Dto\Property[] $propertyOverrides */
    private array $propertyOverrides;

    private Request $request;

    private FilterableRepository $repository;

    private ?TypoScriptFrontendController $frontendController;

    private ?ContentObjectRenderer $cObj;

    public function __construct(
        private readonly UriBuilder $uriBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DatabaseService $databaseService,
        private readonly ExtbasePageTitleProvider $pageTitleProvider,
        private readonly FlexFormSheetsService $flexFormSheetsService,
        ConfigurationManagerInterface $configurationManager,
        RequestBuilder $requestBuilder,
    ) {
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
        // TODO: replace deprecated function call
        $this->cObj = $configurationManager->getContentObject();
        $this->request = $requestBuilder->build($this->getRequest());
        $this->uriBuilder->setRequest($this->request);
        $this->frontendController = $this->request->getAttribute('frontend.controller');
        $this->settings = $configuration['settings'] ?? [];
        $this->extensionName = $configuration['extensionName'];
        $this->pluginName = $configuration['pluginName'];
        $cType = strtolower($this->extensionName . '_' . $this->pluginName);
        $this->tableName = PluginUtility::getTableName($cType);
        $this->disableFilterCount = PluginUtility::getDisableFilterCount($cType);
        $this->propertyOverrides = $this->flexFormSheetsService->getPropertyOverrides($this->settings, $this->cObj?->data['sys_language_uid']);
    }

    /**
     * @param mixed[] $filters
     */
    public function getFilterableList(
        FilterableRepository $repository,
        int $currentPage,
        array $filters = [],
        string $filtersArgumentName = 'filter',
    ): FilterableListResult {
        $this->repository = $repository;
        $this->filtersArgumentName = $filtersArgumentName;
        $queryDatabaseFilters = $this->getQueryDatabaseFilters($filters);
        $predefinedDatabaseFilters = FilterUtility::getPredefinedDatabaseFilters(
            $this->settings,
            $this->tableName
        );
        $databaseFilters = array_merge(
            array_values($predefinedDatabaseFilters),
            array_values($queryDatabaseFilters)
        );
        $listResult = $this->getListResult($currentPage, $databaseFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters($predefinedDatabaseFilters, $queryDatabaseFilters);
        $filterableListResult->setFrontendFilters($frontendFilters);
        $resetFilters = new FilterValue([], $this->settings[FrontendFilterSheets::RESET_FILTERS_LABEL] ?? '');
        $resetFilters->setLink($this->uriBuilder->reset()->uriFor());
        $filterableListResult->setResetFilters($resetFilters);
        /** @var ModifyFilterableListResultEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new ModifyFilterableListResultEvent($this->extensionName, $filterableListResult)
        );
        $filterableListResult = $event->getFilterableListResult();
        $this->addCacheTag($this->tableName);
        return $filterableListResult;
    }

    public function getSelectionList(FilterableRepository $repository, int $currentPage): ListResult
    {
        $this->repository = $repository;
        $recordUids = $this->settings[SelectionSheets::RECORDS];
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
        $this->addCacheTag($this->tableName);
        return $this->getListResult($currentPage, $filters);
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\RepositoryInterface<AbstractEntity> $repository
     */
    public function getDetailResult(
        RepositoryInterface $repository,
        ?AbstractEntity $entity
    ): ?DetailResult {
        $source = $this->settings[DetailSheets::SOURCE];
        $result = new DetailResult();
        $item = null;
        switch ($source) {
            case DetailSheets::SOURCE_DEFAULT:
                $item = $entity;
                break;
            case DetailSheets::SOURCE_RECORD:
                $uid = (int) ($this->settings[DetailSheets::RECORD] ?? null);
                $item = $repository->findByUid($uid);
                break;
            default:
                /** @var \TYPO3\CMS\Core\Routing\PageArguments $routing */
                $routing = $this->request->getAttribute('routing');
                $arguments = $routing->getArguments();

                /** @var ModifyDetailItemEvent $modifyDetailItemEvent */
                $modifyDetailItemEvent = $this->eventDispatcher->dispatch(
                    new ModifyDetailItemEvent($this->extensionName, $source, $arguments)
                );
                $item = $modifyDetailItemEvent->getResult();
                break;
        }
        $result->setItem($item);

        $properties = ControllerUtility::getProperties(
            GeneralUtility::trimExplode(',', $this->settings[DetailSheets::PROPERTIES], true),
            $this->propertyOverrides,
            $this->tableName,
        );
        $result->setProperties($properties);

        /** @var EnrichDetailResultEvent $enrichDetailResultEvent */
        $enrichDetailResultEvent = $this->eventDispatcher->dispatch(
            new EnrichDetailResultEvent($this->extensionName, $result)
        );

        $result = $enrichDetailResultEvent->getDetailResult();

        if ($item) {
            /** @var ModifyDetailPageTitleEvent $modifyDetailPageTitleEvent */
            $modifyDetailPageTitleEvent = $this->eventDispatcher->dispatch(
                new ModifyDetailPageTitleEvent($this->extensionName, $item)
            );

            $this->pageTitleProvider->setTitle($modifyDetailPageTitleEvent->getTitle());

            $this->addCacheTag($this->tableName . '_' . $item->getUid());
        }
        return $result;
    }

    public function addCacheTag(string $cacheTag): void
    {
        $this->frontendController?->addCacheTags([$cacheTag]);
    }

    /**
     * @param DatabaseFilter[] $filters
     */
    private function getListResult(int $currentPage, array $filters = []): ListResult
    {
        $result = new ListResult();
        $limit = (int) ($this->settings[ListSheets::LIMIT] ?? null);
        $orderBy = $this->settings[ListSheets::ORDER_BY] ?? null;
        $orderDirection = $this->settings[ListSheets::ORDER_DIRECTION] ?? null;
        $itemsPerPage = (int) ($this->settings[ListSheets::ITEMS_PER_PAGE] ?? null);

        $properties = ControllerUtility::getProperties(
            GeneralUtility::trimExplode(',', $this->settings[ListSheets::PROPERTIES], true),
            $this->propertyOverrides,
            $this->tableName,
        );
        $result->setProperties($properties);

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
     * @param DatabaseFilter[] $predefinedDatabaseFilters
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @return FrontendFilter[]
     */
    private function getFrontendFilters(array $predefinedDatabaseFilters, array $queryDatabaseFilters): array
    {
        $result = [];
        $filterSettings = $this->settings[FrontendFilterSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[FrontendFilterSheets::FILTER] ?? [];
            $filterName = FilterUtility::getFilterName($filterSetting);

            $disabled = (bool) ($filterSetting[FrontendFilterSheets::DISABLED] ?? false);

            if (
                $disabled ||
                !$filterName
            ) {
                continue;
            }

            $filterValues = [];

            $dynamicValues = (bool) ($filterSetting[FrontendFilterSheets::DYNAMIC_VALUES] ?? false);

            if ($dynamicValues) {
                $fieldNames = GeneralUtility::trimExplode(',', $filterName, true);
                $dynamicFilterValues = $this->cObj ? $this->databaseService->getAvailableFieldValues(
                    $this->cObj->data['sys_language_uid'],
                    $this->tableName,
                    $fieldNames,
                    $this->cObj->data['pages'],
                    $this->cObj->data['recursive'],
                    $predefinedDatabaseFilters,
                ) : [];

                $excludedValues = json_decode($filterSetting[FrontendFilterSheets::EXCLUDED_VALUES], true) ?? [];
                foreach ($excludedValues as $excludedValue) {
                    foreach ($dynamicFilterValues as $key => $dynamicFilterValue) {
                        if ($dynamicFilterValue['value'] === $excludedValue) {
                            unset($dynamicFilterValues[$key]);
                            break;
                        }
                    }
                }

                $filterValues = array_values($dynamicFilterValues);
            } else {
                $filterValues = json_decode($filterSetting[FrontendFilterSheets::VALUES] ?? '', true);
            }

            if (empty($filterValues)) {
                continue;
            }

            $exclusive = (bool) $filterSetting[FrontendFilterSheets::EXCLUSIVE];

            $label = ControllerUtility::getFieldLabel($filterName, $this->propertyOverrides, $this->tableName);

            $resetFilterLabel = $filterSetting[FrontendFilterSheets::RESET_FILTER_LABEL] ?? '';

            $resetFilter = new FilterValue([], $resetFilterLabel);
            $resetFilter->setLink(
                $this->getFrontendFilterValueLink($filterName, $queryDatabaseFilters, [], $exclusive)
            );

            $frontendFilter = new FrontendFilter(
                $filterName,
                $label,
                $resetFilter,
            );

            foreach ($filterValues as $filterValue) {
                $label = $this->getFrontendFilterValueLabel($filterName, $filterValue);
                $value = json_decode($filterValue['value'], true);
                $frontendFilter->addValue(new FilterValue($value, $label));
            }

            foreach ($frontendFilter->getValues() as $filterValue) {
                $value = $filterValue->getValue();

                $active = $this->getFrontendFilterValueActive(
                    $filterName,
                    $queryDatabaseFilters,
                    $value
                );
                $filterValue->setActive($active);

                $link = $this->getFrontendFilterValueLink(
                    $filterName,
                    $queryDatabaseFilters,
                    $value,
                    $exclusive
                );
                $filterValue->setLink($link);

                if (!$this->disableFilterCount) {
                    $count = $this->getFrontendFilterValueCount(
                        $filterSetting,
                        $filterName,
                        $predefinedDatabaseFilters,
                        $queryDatabaseFilters,
                        $value,
                        $exclusive
                    );
                    $filterValue->setCount($count);
                }
            }

            $result[] = $frontendFilter;
        }
        return $result;
    }

    /**
     * @param mixed[] $filterValue
     */
    private function getFrontendFilterValueLabel(string $filterName, array $filterValue): string
    {
        $propertyOverrides = $this->propertyOverrides[$filterName] ?? null;
        $valueOverrides = $propertyOverrides?->getOverrides() ?? [];
        $prefix = $propertyOverrides?->getPrefix() ?? '';
        $suffix = $propertyOverrides?->getSuffix() ?? '';
        return $valueOverrides[$filterValue['value']] ?? $prefix . $filterValue['label'] . $suffix;
    }

    /**
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param mixed[] $value
     */
    private function getFrontendFilterValueActive(
        string $filterName,
        array $queryDatabaseFilters,
        array $value,
    ): bool {
        $databaseFilter = $queryDatabaseFilters[$filterName] ?? null;

        if ($databaseFilter) {
            $databaseFilterValues = $databaseFilter->getValues();
            return count(
                array_filter($databaseFilterValues, function (array $databaseFilterValue) use ($value) {
                    return $databaseFilterValue === $value;
                })
            ) > 0;
        }

        return false;
    }

    /**
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param mixed[] $values
     */
    private function getFrontendFilterValueLink(
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
            array_splice($filters[$filterName], (int) $index, 1);
        } else {
            if ($exclusive) {
                $filters[$filterName] = [$values];
            } else {
                $filters[$filterName][] = $values;
            }
        }
        $filters = array_filter($filters);

        array_walk_recursive($filters, function (mixed $value, string $key) use (&$filterArguments): void {
            $filterArguments[$key][] = $value ?? '';
        });

        $filterArguments = FilterUtility::simplifyQueryParameters($filterArguments);

        return $this->uriBuilder
            ->reset()
            ->uriFor(null, [$this->filtersArgumentName => $filterArguments]);
    }

    /**
     * @param mixed[] $filterSetting
     * @param DatabaseFilter[] $predefinedDatabaseFilters
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param mixed[] $value
     */
    private function getFrontendFilterValueCount(
        array $filterSetting,
        string $filterName,
        array $predefinedDatabaseFilters,
        array $queryDatabaseFilters,
        array $value,
        bool $exclusive
    ): int {
        $tmpFilters = $queryDatabaseFilters;

        $databaseFilter = isset($tmpFilters[$filterName])
            ? clone $tmpFilters[$filterName]
            : FilterUtility::getDatabaseFilter($filterSetting, $filterName, [], $this->tableName);

        $tmpFilters[$filterName] = $databaseFilter;

        $index = array_search($value, $databaseFilter->getValues());
        if ($index === false) {
            if ($exclusive) {
                $databaseFilter->setValues([]);
            }
            $databaseFilter->addValue($value);
        } else {
            $databaseFilterValues = $databaseFilter->getValues();
            array_splice($databaseFilterValues, (int) $index, 1);
            $databaseFilter->setValues($databaseFilterValues);
        }

        $databaseFilters = array_merge(
            array_values($predefinedDatabaseFilters),
            array_values($tmpFilters)
        );

        $queryResult = $this->repository->findByFilters($databaseFilters);
        return $queryResult->count();
    }

    /**
     * @param mixed[] $filters
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

        $filterSettings = $this->settings[FrontendFilterSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[FrontendFilterSheets::FILTER];
            $filterName = FilterUtility::getFilterName($filterSetting);

            if (!$filterName) {
                continue;
            }

            $fields = GeneralUtility::trimExplode(',', $filterName, true);
            if (count($fields) > 1) {
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
                    $this->tableName
                );
            }
        }

        return $result;
    }

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
