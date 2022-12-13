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
use Remind\Extbase\Service\Dto\FilterValue;
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
    private Request $request;
    private ContentObjectRenderer $contentObject;

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
        $filterTable = $this->getFilterTable();
        $repositoryFilters = $this->getRepositoryFilters($filterTable, $filters);
        $listResult = $this->getListResult($repository, $currentPage, $repositoryFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters(
            $repository,
            $repositoryFilters,
            $filtersArgumentName,
            $filterTable
        );
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
        string $filtersArgumentName,
        string $tableName
    ): array {
        $result = [];
        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        $activeFiltersValues = array_map(function (RepositoryFilter $repositoryFilter) {
            return $repositoryFilter->getValues();
        }, $repositoryFilters);
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $fieldName = $filterSetting[ListFiltersSheets::FIELD];

            $filterValuesString = $filterSetting[ListFiltersSheets::AVAILABLE_VALUES] ?? null;
            $exclusive = (bool) $filterSetting[ListFiltersSheets::EXCLUSIVE];

            $filterValues = json_decode($filterValuesString, true);
            if (empty($filterValues)) {
                continue;
            }

            $label = $filterSetting[ListFiltersSheets::LABEL];
            if (!$label) {
                $label = BackendUtility::getItemLabel($tableName, $fieldName);
                if (str_starts_with($label, 'LLL:')) {
                    $label = LocalizationUtility::translate($label);
                }
            }

            $frontendFilter = new FrontendFilter(
                $fieldName,
                $label,
            );

            $activeFilterValues = $activeFiltersValues[$fieldName] ?? [];
            $tmpRepositoryFilters = $repositoryFilters;

            foreach ($filterValues as $filterValue) {
                $filterValue = new FilterValue($filterValue['value'], $filterValue['label']);
                $value = $filterValue->getValue();

                $repositoryFilter = $this->getRepositoryFilter(
                    $filterSetting,
                    $tableName,
                    (!$exclusive) ? $activeFilterValues : [],
                );
                if (!in_array($value, $repositoryFilter->getValues())) {
                    $repositoryFilter->addValue($value);
                }
                $tmpRepositoryFilters[$fieldName] = $repositoryFilter;
                $queryResult = $filterableRepository->findByFilters($tmpRepositoryFilters);
                $count = $queryResult->count();
                $filterValue->setCount($count);
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
    private function getRepositoryFilters(string $tableName, ?array $valueOverrides = []): array
    {
        $result = [];
        $filterSettings = $this->settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER];
            $fieldName = $filterSetting[ListFiltersSheets::FIELD];
            /** @var string $valuesString */
            $valuesString = $filterSetting[ListFiltersSheets::APPLIED_VALUES] ?? null;
            $values = $valueOverrides[$fieldName] ?? json_decode($valuesString, true);
            if (!empty($values)) {
                $result[$fieldName] = $this->getRepositoryFilter($filterSetting, $tableName, $values);
            }
        }
        return $result;
    }

    private function getRepositoryFilter(array $filterSetting, string $tableName, ?array $values = []): RepositoryFilter
    {
        $fieldName = $filterSetting[ListFiltersSheets::FIELD];
        /** @var Conjunction $conjunction */
        $conjunction = Conjunction::from($filterSetting[ListFiltersSheets::CONJUNCTION] ?? Conjunction::OR->value);
        $fieldTca = BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);

        return new RepositoryFilter(
            $fieldName,
            $values,
            isset($fieldTca['MM']),
            $conjunction
        );
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
