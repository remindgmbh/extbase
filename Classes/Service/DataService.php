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
    ): FilterableListResult {
        $repositoryFilters = $this->getRepositoryFilters($filters);
        $listResult = $this->getListResult($repository, $currentPage, $repositoryFilters);
        $filterableListResult = new FilterableListResult($listResult);
        $frontendFilters = $this->getFrontendFilters($repository, $repositoryFilters);
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
    private function getFrontendFilters(FilterableRepository $filterableRepository, array $repositoryFilters): array
    {
        $filtersSettings = $this->settings[ListFiltersSheets::FRONTEND_FILTERS] ?? [];
        $filtersConfig = PluginUtility::getFilters($this->extensionName);

        $result = [];
        foreach ($filtersSettings as $fieldName => $filterSetting) {
            $filterConfig = $filtersConfig[$fieldName];
            $filterValueString = $filterSetting[ListFiltersSheets::VALUES] ?? null;

            if (!$filterValueString) {
                continue;
            }

            $label = $filterConfig['label'];
            $frontendFilter = new FrontendFilter(
                $fieldName,
                str_starts_with($label, 'LLL:') ? LocalizationUtility::translate($label) : $label,
                $filterConfig['mm']
            );

            if (!$filterConfig['table']) {
                $filterValues = GeneralUtility::trimExplode(PHP_EOL, $filterValueString, true);
            } else {
                $filterRepositoryClassName = $filterConfig['repository'];
                /** @var \TYPO3\CMS\Extbase\Persistence\Repository $filterRepository */
                $filterRepository = GeneralUtility::makeInstance($filterRepositoryClassName);
                $filterValues = GeneralUtility::intExplode(',', $filterValueString, true);
                $filterValues = array_map(function (int $uid) use ($filterRepository) {
                    return $filterRepository->findByUid($uid);
                }, $filterValues);
            }

            $repositoryFilter = $repositoryFilters[$fieldName] ?? null;
            $activeValues = [];
            if ($repositoryFilter) {
                $activeValues = $repositoryFilter->getValues();
            }
            $tmpRepositoryFilters = array_filter($repositoryFilters, function (string $key) use ($fieldName) {
                return $key !== $fieldName;
            }, ARRAY_FILTER_USE_KEY);

            foreach ($filterValues as $value) {
                $filterValue = new FilterValue($value);
                $argumentValue = $filterValue->getArgumentValue();
                $tmpRepositoryFilters[$fieldName] = new RepositoryFilter(
                    $fieldName,
                    [$argumentValue],
                    $frontendFilter->isMm(),
                    Conjunction::OR
                );
                $queryResult = $filterableRepository->findByFilters($tmpRepositoryFilters);
                $count = $queryResult->count();
                $filterValue->setDisabled(!$count);
                $isActive = in_array($argumentValue, $activeValues);
                $filterValue->setActive($isActive);
                $frontendFilter->addValue($filterValue);
            }

            $result[] = $frontendFilter;
        }
        return $result;
    }

    /**
     * @return RepositoryFilter[]
     */
    private function getRepositoryFilters(?array $valueOverrides = []): array
    {
        $result = [];
        $filters = PluginUtility::getFilters($this->extensionName);
        $filterSettings = $this->settings[ListFiltersSheets::BACKEND_FILTERS] ?? [];
        foreach ($filters as $fieldName => $filterConfig) {
            /** @var string $values */
            $values = $valueOverrides[$fieldName] ?? $filterSettings[$fieldName][ListFiltersSheets::VALUES] ?? null;
            if ($values) {
                /** @var Conjunction $conjunction */
                $conjunction = Conjunction::from(
                    $filterSettings[ListFiltersSheets::CONJUNCTION] ?? Conjunction::OR->value
                );
                if ($filterConfig['table']) {
                    $values = GeneralUtility::intExplode(',', $values, true);
                } else {
                    $values = GeneralUtility::trimExplode(PHP_EOL, $values, true);
                }
                $result[$fieldName] = new RepositoryFilter(
                    $fieldName,
                    $values,
                    $filterConfig['mm'] ?? false,
                    $conjunction
                );
            }
        }
        return $result;
    }
}
