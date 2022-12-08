<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Extbase\Domain\Repository\Dto\Conjunction;
use Remind\Extbase\Domain\Repository\Dto\RepositoryFilter;
use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\FlexForms\DetailDataSheets;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\SelectionDataSheets;
use Remind\Extbase\Service\Dto\FilterableListResult;
use Remind\Extbase\Service\Dto\FilterValue;
use Remind\Extbase\Service\Dto\FrontendFilter;
use Remind\Extbase\Service\Dto\ListResult;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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

class DataService
{
    private array $settings;
    private string $extensionName;
    private string $pluginName;
    private Request $request;

    public function __construct(
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly UriBuilder $uriBuilder,
        ConfigurationManagerInterface $configurationManager,
        Request $request,
        RequestBuilder $requestBuilder,
    ) {
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
        $this->settings = $configuration['settings'] ?? [];
        $this->extensionName = $configuration['extensionName'];
        $this->pluginName = $configuration['pluginName'];
        $this->request = $requestBuilder->build($request);
        $this->uriBuilder->setRequest($this->request);
    }

    public function getFilterableList(
        FilterableRepository $repository,
        int $currentPage,
        ?array $filters = null,
        ?string $filtersArgumentName = 'filter',
    ): FilterableListResult {
        $repositoryFilters = $this->getRepositoryFilters($filters);
        $listResult = $this->getListResult($repository, $currentPage, $repositoryFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters($repository, $repositoryFilters, $filtersArgumentName);
        $filterableListResult->setFrontendFilters($frontendFilters);
        return $filterableListResult;
    }

    public function getSelectionList(FilterableRepository $repository, int $currentPage,): ListResult
    {
        $recordUids = $this->settings[SelectionDataSheets::RECORDS];
        $recordUids = GeneralUtility::intExplode(',', $recordUids, true);
        $filters = [new RepositoryFilter('uid', $recordUids, false, Conjunction::OR)];
        return $this->getListResult($repository, $currentPage, $filters);
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
     * @param FilterableRepository $repository
     * @param int $currentPage
     * @param RepositoryFilter[] $filters
     */
    private function getListResult(FilterableRepository $repository, int $currentPage, ?array $filters = []): ListResult
    {
        $result = new ListResult();
        $limit = (int) ($this->settings[ListSheets::LIMIT] ?? null);
        $orderBy = $this->settings[ListSheets::ORDER_BY] ?? null;
        $orderDirection = $this->settings[ListSheets::ORDER_DIRECTION] ?? null;
        $itemsPerPage = (int) ($this->settings[ListSheets::ITEMS_PER_PAGE] ?? null);

        $queryResult = $repository->findByFilters(
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
     * @param RepositoryFilter[] $repositoryFilters
     * @return FrontendFilter[]
     */
    private function getFrontendFilters(
        FilterableRepository $filterableRepository,
        array $repositoryFilters,
        string $filtersArgumentName
    ): array {
        $result = [];
        $feFiltersSettings = $this->settings[ListFiltersSheets::FRONTEND_FILTERS] ?? [];
        $filtersConfigs = PluginUtility::getFilters($this->extensionName, $this->pluginName);
        $activeFiltersValues = array_map(function (RepositoryFilter $repositoryFilter) {
            return $repositoryFilter->getValues();
        }, $repositoryFilters);
        foreach ($filtersConfigs as $filterConfig) {
            $fieldName = $filterConfig[PluginUtility::FILTER_FIELD_NAME];
            $tableName = $filterConfig[PluginUtility::FILTER_TABLE_NAME];

            $feFilterSetting = $feFiltersSettings[$fieldName];
            $filterValuesString = $feFilterSetting[ListFiltersSheets::VALUES] ?? null;
            $exclusive = (bool) $feFilterSetting[ListFiltersSheets::EXCLUSIVE];

            if (!$filterValuesString) {
                continue;
            }

            $frontendFilter = new FrontendFilter(
                $fieldName,
                $feFilterSetting[ListFiltersSheets::LABEL],
            );

            $filterValues = json_decode($filterValuesString, true);

            $activeFilterValues = $activeFiltersValues[$fieldName] ?? [];
            $tmpRepositoryFilters = $repositoryFilters;

            foreach ($filterValues as $filterValue) {
                $filterValue = new FilterValue($filterValue['value'], $filterValue['label']);
                $value = $filterValue->getValue();

                $repositoryFilter = $this->getRepositoryFilter(
                    $fieldName,
                    $tableName,
                    (!$exclusive) ? $activeFilterValues : [],
                );
                if (!in_array($value, $repositoryFilter->getValues())) {
                    $repositoryFilter->addValue($value);
                }
                $tmpRepositoryFilters[$fieldName] = $repositoryFilter;
                $queryResult = $filterableRepository->findByFilters($tmpRepositoryFilters);
                $count = $queryResult->count();
                $filterValue->setDisabled(!$count);
                $isActive = in_array($value, $activeFilterValues);
                $filterValue->setActive($isActive);

                $url = $this->buildFrontendFilterUrl(
                    $activeFiltersValues,
                    $fieldName,
                    $value,
                    $filtersArgumentName,
                    $exclusive
                );

                $filterValue->setLink($url);
                $frontendFilter->addValue($filterValue);
            }

            $result[] = $frontendFilter;
        }
        return $result;
    }

    private function buildFrontendFilterUrl(
        array $activeFilterValues,
        string $fieldName,
        int|string $value,
        string $filtersArgumentName,
        bool $exclusive
    ): string {
        $index = array_search($value, $activeFilterValues[$fieldName] ?? []);
        if ($index !== false) {
            // remove argument if it is already active so the link removes the filter
            array_splice($activeFilterValues[$fieldName], $index, 1);
        } else {
            if ($exclusive) {
                $activeFilterValues[$fieldName] = [$value];
            } else {
                $activeFilterValues[$fieldName][] = $value;
            }
        }

        return $this->uriBuilder
            ->reset()
            ->uriFor(null, [$filtersArgumentName => $activeFilterValues]);
    }

    /**
     * @return RepositoryFilter[]
     */
    private function getRepositoryFilters(?array $valueOverrides = []): array
    {
        $result = [];
        $filterConfigs = PluginUtility::getFilters($this->extensionName, $this->pluginName);
        $filterSettings = $this->settings[ListFiltersSheets::BACKEND_FILTERS] ?? [];
        foreach ($filterConfigs as $filterConfig) {
            $fieldName = $filterConfig[PluginUtility::FILTER_FIELD_NAME];
            $filterSetting = $filterSettings[$fieldName];
            /** @var string $valuesString */
            $valuesString = $filterSetting[ListFiltersSheets::VALUES] ?? null;
            $values = $valueOverrides[$fieldName] ?? json_decode($valuesString, true);
            if (!empty($values)) {
                $tableName = $filterConfig[PluginUtility::FILTER_TABLE_NAME];
                $result[$fieldName] = $this->getRepositoryFilter($fieldName, $tableName, $values);
            }
        }
        return $result;
    }

    private function getRepositoryFilter(string $fieldName, string $tableName, ?array $values = []): RepositoryFilter
    {
        $filterSettings = $this->settings[ListFiltersSheets::BACKEND_FILTERS] ?? [];
        $filterSetting = $filterSettings[$fieldName];
        /** @var Conjunction $conjunction */
        $conjunction = Conjunction::from(
            $filterSetting[ListFiltersSheets::CONJUNCTION] ?? Conjunction::OR->value
        );
        $fieldTca = BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);

        return new RepositoryFilter(
            $fieldName,
            $values,
            isset($fieldTca['MM']),
            $conjunction
        );
    }
}
