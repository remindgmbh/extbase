<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\Dto\Conjunction;
use Remind\Extbase\Dto\FilterData;
use Remind\Extbase\Dto\ListData;
use Remind\Extbase\Dto\ListFilter;
use Remind\Extbase\FlexForms\DetailDataSheet;
use Remind\Extbase\FlexForms\FilterSheet;
use Remind\Extbase\FlexForms\ListFilterSheet;
use Remind\Extbase\FlexForms\ListSheet;
use Remind\Extbase\FlexForms\SelectionDataSheet;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DataService
{
    private array $settings;
    private string $extensionName;

    public function __construct(
        ConfigurationManagerInterface $configurationManager,
        private readonly Request $request,
        private readonly PersistenceManagerInterface $persistenceManager
    ) {
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
        $this->settings = $configuration['settings'] ?? [];
        $this->extensionName = $configuration['extensionName'];
    }

    public function getFilterableList(
        FilterableRepository $repository,
        int $currentPage,
        ?array $filters = null
    ): ListData {
        $listFilters = $this->getListFilters($filters);
        return $this->getListData($repository, $currentPage, $listFilters);
    }

    public function getSelectionList(FilterableRepository $repository, int $currentPage,): ListData
    {
        $recordUids = $this->settings[SelectionDataSheet::RECORDS];
        $recordUids = GeneralUtility::intExplode(',', $recordUids, true);
        $filters = [new ListFilter(false, Conjunction::OR, 'uid', $recordUids)];
        return $this->getListData($repository, $currentPage, $filters);
    }

    /**
     * @return FilterData[]
     */
    public function getFilters(): array
    {
        $filtersSettings = $this->settings[FilterSheet::FILTER] ?? [];
        $filters = PluginUtility::getFilters($this->extensionName);

        $result = [];
        foreach ($filtersSettings as $fieldName => $filterSetting) {
            $filter = $filters[$fieldName];
            $filterValueString = $filterSetting[FilterSheet::VALUES] ?? null;

            if (!$filterValueString) {
                continue;
            }

            $filterData = new FilterData();
            $filterData->setName($fieldName);
            $filterData->setLabel(LocalizationUtility::translate($filter['label']));

            $filterValues = GeneralUtility::intExplode(',', $filterValueString, true);

            foreach ($filterValues as $uid) {
                $repositoryClassName = $filter['repository'];
                /** @var \TYPO3\CMS\Extbase\Persistence\Repository $repository */
                $repository = GeneralUtility::makeInstance($repositoryClassName);
                $object = $repository->findByUid($uid);
                $filterData->addValue($object);
            }

            $result[] = $filterData;
        }
        return $result;
    }

    public function getDetailEntity(
        RepositoryInterface $repository,
        ?AbstractEntity $entity,
        callable $callback
    ): ?AbstractEntity {
        $source = $this->settings[DetailDataSheet::SOURCE];
        switch ($source) {
            case DetailDataSheet::SOURCE_DEFAULT:
                return $entity;
            case DetailDataSheet::SOURCE_RECORD:
                $uid = (int) ($this->settings[DetailDataSheet::RECORD] ?? null);
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
        $argument = $source['argument'] ?? null;
        $repositoryClassName = $source['repository'] ?? null;

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

    private function getListData(FilterableRepository $repository, int $currentPage, ?array $filters = []): ListData
    {
        $result = new ListData();

        $limit = (int) ($this->settings[ListSheet::LIMIT] ?? null);
        $orderBy = $this->settings[ListSheet::ORDER_BY] ?? null;
        $orderDirection = $this->settings[ListSheet::ORDER_DIRECTION] ?? null;
        $itemsPerPage = (int) ($this->settings[ListSheet::ITEMS_PER_PAGE] ?? null);

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
     * @return ListFilter[]
     */
    private function getListFilters(?array $valueOverrides = []): array
    {
        $allowedFilterFields = GeneralUtility::trimExplode(',', $this->settings['allowedFilterFields'] ?? '', true);
        $result = [];
        foreach ($this->settings[ListFilterSheet::FILTER] ?? [] as $fieldName => $filterConfig) {
            $value = $valueOverrides[$fieldName] ?? $filterConfig[ListFilterSheet::VALUES];
            if (in_array($fieldName, $allowedFilterFields) && $value) {
                /** @var Conjunction $conjunction */
                $conjunction = Conjunction::from($filterConfig[ListFilterSheet::CONJUNCTION] ?? Conjunction::OR->value);
                if ($filterConfig['multi'] ?? false) {
                    $value = GeneralUtility::trimExplode(',', $value, true);
                }
                $result[] = new ListFilter((bool) ($filterConfig['mm'] ?? false), $conjunction, $fieldName, $value);
            }
        }
        return $result;
    }
}
