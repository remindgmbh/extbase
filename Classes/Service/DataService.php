<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Remind\Extbase\Domain\Repository\Dto\Conjunction;
use Remind\Extbase\Domain\Repository\Dto\RepositoryFilter;
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
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class DataService
{
    private array $settings;
    private string $extensionName;
    private string $pluginName;
    private string $filterTable;
    private string $filtersArgumentName;
    private Request $request;
    private FilterableRepository $repository;

    public function __construct(
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly UriBuilder $uriBuilder,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
        ConfigurationManagerInterface $configurationManager,
        RequestBuilder $requestBuilder,
    ) {
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
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
        $queryRepositoryFilters = $this->getQueryRepositoryFilters($filters);
        $appliedRepositoryFilters = $this->getAppliedRepositoryFilters();
        $repositoryFilters = array_merge(
            array_values($appliedRepositoryFilters),
            array_values($queryRepositoryFilters)
        );
        $listResult = $this->getListResult($currentPage, $repositoryFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters($appliedRepositoryFilters, $queryRepositoryFilters);
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
            new RepositoryFilter('uid', array_map(function (int $uid) {
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
     * @param RepositoryFilter[] $filters
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
     * @param RepositoryFilter[] $appliedRepositoryFilters
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @return FrontendFilter[]
     */
    private function getFrontendFilters(array $appliedRepositoryFilters, array $queryRepositoryFilters): array
    {
        $result = [];
        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER] ?? [];
            $filterName = $this->getFilterName($filterSetting);

            $disabled = (bool) ($filterSetting[ListFiltersSheets::DISABLED] ?? false);
            $filterValues = json_decode($filterSetting[ListFiltersSheets::AVAILABLE_VALUES] ?? '', true);

            if ($disabled || empty($filterValues)) {
                continue;
            }

            $exclusive = (bool) $filterSetting[ListFiltersSheets::EXCLUSIVE];

            $label = $this->getFrontendFilterLabel($filterName, $filterSetting);

            $allValuesLabel = $filterSetting[ListFiltersSheets::ALL_VALUES_LABEL] ?? '';

            $allValues = new FilterValue([], $allValuesLabel);
            $allValues->setLink($this->getFrontendFilterLink($filterName, $queryRepositoryFilters, [], $exclusive));

            $frontendFilter = new FrontendFilter(
                $filterName,
                $label,
                $allValues,
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
                    $queryRepositoryFilters,
                    $value
                );
                $filterValue->setActive($active);

                $link = $this->getFrontendFilterLink(
                    $filterName,
                    $queryRepositoryFilters,
                    $value,
                    $exclusive
                );
                $filterValue->setLink($link);

                $count = $this->getFrontendFilterCount(
                    $filterName,
                    $appliedRepositoryFilters,
                    $queryRepositoryFilters,
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
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @param array $value
     * @return bool
     */
    private function getFrontendFilterActive(
        string $filterName,
        array $queryRepositoryFilters,
        array $value,
    ): bool {
        $repositoryFilter = $queryRepositoryFilters[$filterName] ?? null;

        if ($repositoryFilter) {
            $repositoryFilterValues = $repositoryFilter->getValues();
            return count(
                array_filter($repositoryFilterValues, function (array $repositoryFilterValue) use ($value) {
                    return $repositoryFilterValue == $value;
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
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @param array $values
     * @param bool $exclusive
     * @return string
     */
    private function getFrontendFilterLink(
        string $filterName,
        array $queryRepositoryFilters,
        array $values,
        bool $exclusive,
    ): string {
        $filterArguments = [];

        $filters = array_map(function (RepositoryFilter $repositoryFilter) {
            return $repositoryFilter->getValues();
        }, $queryRepositoryFilters);

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
     * @param string $filterName
     * @param RepositoryFilter[] $appliedRepositoryFilters
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @param array $value
     * @param bool $exclusive
     * @return int
     */
    private function getFrontendFilterCount(
        string $filterName,
        array $appliedRepositoryFilters,
        array $queryRepositoryFilters,
        array $value,
        bool $exclusive
    ): int {
        $tmpFilters = $queryRepositoryFilters;

        $repositoryFilter = isset($tmpFilters[$filterName])
            ? clone ($tmpFilters[$filterName])
            : $this->getRepositoryFilter($filterName, []);
        $tmpFilters[$filterName] = $repositoryFilter;

        $index = array_search($value, $repositoryFilter->getValues());
        if ($index === false) {
            if ($exclusive) {
                $repositoryFilter->setValues([]);
            }
            $repositoryFilter->addValue($value);
        } else {
            $repositoryFilterValues = $repositoryFilter->getValues();
            array_splice($repositoryFilterValues, $index, 1);
            $repositoryFilter->setValues($repositoryFilterValues);
        }

        $repositoryFilters = array_merge(
            array_values($appliedRepositoryFilters),
            array_values($tmpFilters)
        );

        $queryResult = $this->repository->findByFilters($repositoryFilters);
        return $queryResult->count();
    }

    private function getRepositoryFilter(string $filterName, array $values): RepositoryFilter
    {
        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        $filterSetting = current(array_filter($filterSettings, function (array $filterSetting) use ($filterName) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $name = $this->getFilterName($filterSetting);
            return $name === $filterName;
        }))[ListFiltersSheets::FILTER];
        $conjunction = Conjunction::from($filterSetting[ListFiltersSheets::CONJUNCTION] ?? Conjunction::OR->value);
        $fieldTca = BackendUtility::getTcaFieldConfiguration($this->filterTable, $filterName);
        return new RepositoryFilter(
            $filterName,
            $values,
            isset($fieldTca['MM']),
            $conjunction
        );
    }

    /**
     * @return RepositoryFilter[]
     */
    private function getAppliedRepositoryFilters(): array
    {
        $result = [];

        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER] ?? [];
            $filterName = $this->getFilterName($filterSetting);

            $disabled = (bool) ($filterSetting[ListFiltersSheets::DISABLED] ?? false);

            $values = array_map(
                function (string $value) {
                    return json_decode($value, true);
                },
                json_decode($filterSetting[ListFiltersSheets::APPLIED_VALUES] ?? '', true) ?? []
            );

            if ($disabled || empty($values)) {
                continue;
            }

            $result[$filterName] = $values;
        }
        return $this->getRepositoryFilters($result);
    }

    /**
     * @return RepositoryFilter[]
     */
    private function getQueryRepositoryFilters(array $filters): array
    {
        $filters = FilterUtility::normalizeQueryParameters($filters);

        foreach ($filters as $fieldName => &$values) {
            foreach ($values as &$value) {
                $value = [$fieldName => $value];
            }
        }

        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $allowMultipleFields = (bool) $filterSetting[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
            if ($allowMultipleFields) {
                $filterName = $filterSetting[ListFiltersSheets::FIELDS];
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
        }

        return $this->getRepositoryFilters($filters);
    }

    /**
     * @return RepositoryFilter[]
     */
    private function getRepositoryFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $filterName => $filter) {
            $result[$filterName] = $this->getRepositoryFilter($filterName, $filter);
        }
        return $result;
    }

    private function getFilterName(array $filterSetting): string
    {
        $allowMultipleFields = (bool) $filterSetting[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
        return $filterSetting[$allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD];
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
