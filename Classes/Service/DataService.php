<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Extbase\Backend\ItemsProc;
use Remind\Extbase\Domain\Repository\Dto\Conjunction;
use Remind\Extbase\Domain\Repository\Dto\RepositoryFilter;
use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\FlexForms\DetailDataSheets;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\SelectionDataSheets;
use Remind\Extbase\Service\Dto\FilterableListResult;
use Remind\Extbase\Service\Dto\FrontendFilter;
use Remind\Extbase\Service\Dto\ListResult;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\RequestBuilder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class DataService
{
    private array $settings;
    private string $extensionName;
    private string $pluginName;
    private string $filterTable;
    private string $filtersArgumentName;
    private Request $request;
    private ContentObjectRenderer $contentObject;
    private FilterableRepository $repository;

    public function __construct(
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly UriBuilder $uriBuilder,
        private readonly FlexFormTools $flexFormTools,
        ConfigurationManagerInterface $configurationManager,
        Request $request,
        RequestBuilder $requestBuilder
    ) {
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
        $this->settings = $configuration['settings'] ?? [];
        $this->extensionName = $configuration['extensionName'];
        $this->pluginName = $configuration['pluginName'];
        $this->request = $requestBuilder->build($request);
        $this->uriBuilder->setRequest($this->request);
        $this->contentObject = $configurationManager->getContentObject();
    }

    public function getFilterableList(
        FilterableRepository $repository,
        int $currentPage,
        ?array $filters = null,
        ?string $filtersArgumentName = 'filter',
    ): FilterableListResult {
        $this->repository = $repository;
        $this->filtersArgumentName = $filtersArgumentName;
        $this->filterTable = $this->getFilterTable();
        $queryRepositoryFilters = $this->getQueryRepositoryFilters($filters);
        $appliedRepositoryFilters = $this->getAppliedRepositoryFilters();
        $repositoryFilters = array_merge($appliedRepositoryFilters, $queryRepositoryFilters);
        $listResult = $this->getListResult($currentPage, $repositoryFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters($appliedRepositoryFilters, $queryRepositoryFilters);
        $filterableListResult->setFrontendFilters($frontendFilters);
        return $filterableListResult;
    }

    public function getSelectionList(FilterableRepository $repository, int $currentPage): ListResult
    {
        $this->repository = $repository;
        $recordUids = $this->settings[SelectionDataSheets::RECORDS];
        $recordUids = GeneralUtility::intExplode(',', $recordUids, true);
        $filters = [new RepositoryFilter('uid', ['uid' => $recordUids], false, Conjunction::OR)];
        return $this->getListResult($currentPage, $filters);
    }

    public function getDetailEntity(
        RepositoryInterface $repository,
        ?AbstractEntity $entity,
        callable $callback
    ): ?AbstractEntity {
        $source = $this->settings[DetailDataSheets::SOURCE];
        switch ($source) {
            case DetailDataSheets::SOURCE_DEFAULT:
                return $entity;
            case DetailDataSheets::SOURCE_RECORD:
                $uid = (int) ($this->settings[DetailDataSheets::RECORD] ?? null);
                return $repository->findByUid($uid);
            default:
                /** @var \TYPO3\CMS\Core\Routing\PageArguments $routing */
                $routing = $this->request->getAttribute('routing');
                $arguments = $routing->getArguments();

                return $this->getDetailEntityBySource($this->extensionName, $source, $arguments, $callback) ?? null;
        }
    }

    public function getDetailEntityBySource(
        string $extensionName,
        string $pluginSignature,
        array $arguments,
        callable $callback
    ) {
        $sources = PluginUtility::getDetailSources($extensionName);
        $source = $sources[$pluginSignature] ?? [];
        $argument = $source[PluginUtility::DETAIL_SOURCE_ARGUMENT] ?? null;
        $repositoryClassName = $source[PluginUtility::DETAIL_SOURCE_REPOSITORY] ?? null;

        if ($argument && $repositoryClassName) {
            $uid = (int) ($arguments[$pluginSignature][$argument] ?? null);

            if ($uid) {
                /** @var \TYPO3\CMS\Extbase\Persistence\Repository $repository */
                $repository = GeneralUtility::makeInstance($repositoryClassName);
                $object = $repository->findByUid($uid);
                return $callback($object);
            }
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
        $result->setQueryResult($queryResult);
        $result->setCount($queryResult->count());

        if ($itemsPerPage) {
            $paginator = new QueryResultPaginator($queryResult, $currentPage, $itemsPerPage);
            $pagination = new SimplePagination($paginator);
            $paginatedQueryResult = $paginator->getPaginatedItems();
            $result->setPagination($pagination);
            $result->setQueryResult($paginatedQueryResult);
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
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $filterName = $this->getFilterName($filterSetting);

            $filterValuesString = $filterSetting[ListFiltersSheets::AVAILABLE_VALUES] ?? null;
            $exclusive = (bool) $filterSetting[ListFiltersSheets::EXCLUSIVE];

            if (!$filterValuesString) {
                continue;
            }

            $label = $filterSetting[ListFiltersSheets::LABEL];
            if (!$label) {
                $label = BackendUtility::getItemLabel($this->filterTable, $filterName);
                if (str_starts_with($label, 'LLL:')) {
                    $label = LocalizationUtility::translate($label);
                }
            }

            $frontendFilter = new FrontendFilter(
                $filterName,
                $label,
                $filterValuesString
            );

            foreach ($frontendFilter->getValues() as $filterValue) {
                $value = $filterValue->getValue();

                $active = $this->getFrontendFilterActive(
                    $filterName,
                    $appliedRepositoryFilters,
                    $queryRepositoryFilters,
                    $value
                );
                $filterValue->setActive($active);

                $link = $this->getFrontendFilterLink($filterName, $queryRepositoryFilters, $value, $exclusive);
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
     * @param RepositoryFilter[] $appliedRepositoryFilters
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @param mixed $value
     * @return bool
     */
    private function getFrontendFilterActive(
        string $filterName,
        array $appliedRepositoryFilters,
        array $queryRepositoryFilters,
        mixed $value,
    ): bool {
        /**
         * @var RepositoryFilter[] $repositoryFilters
         */
        $repositoryFilters = array_merge($appliedRepositoryFilters, $queryRepositoryFilters);

        $repositoryFilter = $repositoryFilters[$filterName] ?? null;

        if ($repositoryFilter) {
            $repositoryFilterValues = $repositoryFilter->getValues();
            $fieldNames = GeneralUtility::trimExplode(',', $filterName);
            return count(
                array_filter(
                    $repositoryFilterValues,
                    function (array $repositoryFilterValue) use ($value, $fieldNames) {
                        if (count($fieldNames) > 1) {
                            return $repositoryFilterValue == $value;
                        } else {
                            return current($repositoryFilterValue) == $value;
                        }
                    }
                )
            ) > 0;
        }

        return false;
    }

    /**
     * @param string $filterName
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @param mixed $value
     * @param bool $exclusive
     * @return string
     */
    private function getFrontendFilterLink(
        string $filterName,
        array $queryRepositoryFilters,
        mixed $value,
        bool $exclusive,
    ): string {
        $filterArguments = [];

        $activeFilterValues = array_map(function (RepositoryFilter $repositoryFilter) {
            return $repositoryFilter->getValues();
        }, $queryRepositoryFilters);

        foreach ($activeFilterValues as $activeFilterKey => $activeFilterValue) {
            $fieldNames = GeneralUtility::trimExplode(',', $activeFilterKey);
            if (count($fieldNames) === 1) {
                $filterArguments[$activeFilterKey] = array_map(function (array $value) use ($activeFilterKey) {
                    return $value[$activeFilterKey];
                }, $activeFilterValue);
            } else {
                $filterArguments[$activeFilterKey] = $activeFilterValue;
            }
        }

        $index = array_search($value, $filterArguments[$filterName] ?? []);
        if ($index !== false) {
            // remove argument if it is already active so the link removes the filter
            array_splice($filterArguments[$filterName], $index, 1);
        } else {
            if ($exclusive) {
                $filterArguments[$filterName] = [$value];
            } else {
                $filterArguments[$filterName][] = $value;
            }
        }

        // if only one argument is defined remove [0] from query parameter
        foreach ($filterArguments as $activeFilterKey => $activeFilterValue) {
            if (count($activeFilterValue) === 1) {
                $filterArguments[$activeFilterKey] = $activeFilterValue[0];
            }
        }

        return $this->uriBuilder
            ->reset()
            ->uriFor(null, [$this->filtersArgumentName => $filterArguments]);
    }

    /**
     * @param string $filterName
     * @param RepositoryFilter[] $appliedRepositoryFilters
     * @param RepositoryFilter[] $queryRepositoryFilters
     * @param mixed $value
     * @param bool $exclusive
     * @return int
     */
    private function getFrontendFilterCount(
        string $filterName,
        array $appliedRepositoryFilters,
        array $queryRepositoryFilters,
        mixed $value,
        bool $exclusive
    ): int {
        /**
         * @var RepositoryFilter[] $repositoryFilters
         */
        $repositoryFilters = array_merge($appliedRepositoryFilters, $queryRepositoryFilters);

        if (count(GeneralUtility::trimExplode(',', $filterName)) === 1) {
            $value = [$filterName => $value];
        }

        $repositoryFilter = isset($repositoryFilters[$filterName]) ?
            clone($repositoryFilters[$filterName]) :
            $this->getRepositoryFilter($filterName, []);
        $repositoryFilters[$filterName] = $repositoryFilter;

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
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $allowMultipleFields = (bool) $filterSetting[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
            $filterName = $this->getFilterName($filterSetting);
            /** @var string $valuesString */
            $valuesString = $filterSetting[ListFiltersSheets::APPLIED_VALUES] ?? null;
            $values = json_decode($valuesString, true);
            if (empty($values)) {
                continue;
            }

            if ($allowMultipleFields) {
                $result[$filterName] = array_map(function (string $value) {
                    return json_decode(htmlspecialchars_decode($value), true);
                }, $values);
            } else {
                $result[$filterName] = array_map(function (string $value) use ($filterName) {
                    return [$filterName => $value];
                }, $values);
            }
        }
        return $this->getRepositoryFilters($result);
    }

    /**
     * @return RepositoryFilter[]
     */
    private function getQueryRepositoryFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $filterName => $firstLevel) {
            $result[$filterName] = [];
            if (is_array($firstLevel)) {
                $allowMultipleFields = count(array_filter(array_keys($firstLevel), 'is_string')) > 0;
                if ($allowMultipleFields) {
                    $result[$filterName][] = $this->getFieldValues($filterName, $firstLevel);
                } else {
                    foreach ($firstLevel as $secondLevel) {
                        if (is_array($secondLevel)) {
                            $result[$filterName][] = $this->getFieldValues($filterName, $secondLevel);
                        } else {
                            $result[$filterName][] = [$filterName => $secondLevel];
                        }
                    }
                }
            } else {
                $result[$filterName] = [[$filterName => $firstLevel]];
            }
        }
        return $this->getRepositoryFilters($result);
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

    private function getFieldValues(string $filterName, array $values): array
    {
        $fieldNames = GeneralUtility::trimExplode(',', $filterName);
        $fieldValues = [];
        foreach ($fieldNames as $fieldName) {
            $fieldValues[$fieldName] = $values[$fieldName];
        }
        return $fieldValues;
    }

    private function getFilterName(array $filterSetting): string
    {
        $allowMultipleFields = (bool) $filterSetting[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
        return $filterSetting[$allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD];
    }

    private function getFilterTable(): ?string
    {
        $tca = $GLOBALS['TCA']['tt_content']['columns']['pi_flexform'];
        $dataStructureIdentifier = $this->flexFormTools->getDataStructureIdentifier(
            $tca,
            'tt_content',
            'pi_flexform',
            $this->contentObject->data
        );
        $dataStructure = $this->flexFormTools->parseDataStructureByIdentifier($dataStructureIdentifier);
        $listFiltersSheet = $dataStructure['sheets'][ListFiltersSheets::SHEET_ID] ?? null;
        if ($listFiltersSheet) {
            return $listFiltersSheet
                ['ROOT']
                ['el']
                ['settings.' . ListFiltersSheets::FILTERS]
                ['el']
                [ListFiltersSheets::FILTER]
                ['el']
                [ListFiltersSheets::FIELD]
                ['config']
                [ItemsProc::PARAMETERS]
                [ItemsProc::PARAMETER_TABLE_NAME];
        }
        return null;
    }
}
