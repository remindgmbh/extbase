<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\Event\CustomDetailEntitySourceEvent;
use Remind\Extbase\Event\EnrichDetailResultEvent;
use Remind\Extbase\Event\ModifyFilterableListResultEvent;
use Remind\Extbase\FlexForms\DetailSheets;
use Remind\Extbase\FlexForms\FrontendFilterSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\PropertyOverrideSheets;
use Remind\Extbase\FlexForms\SelectionSheets;
use Remind\Extbase\Service\Dto\DetailResult;
use Remind\Extbase\Service\Dto\FilterableListResult;
use Remind\Extbase\Service\Dto\FilterValue;
use Remind\Extbase\Service\Dto\FrontendFilter;
use Remind\Extbase\Service\Dto\ListResult;
use Remind\Extbase\Service\Dto\Property;
use Remind\Extbase\Utility\Dto\Conjunction;
use Remind\Extbase\Utility\Dto\DatabaseFilter;
use Remind\Extbase\Utility\FilterUtility;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Service\FlexFormService;
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
    private string $tableName;
    private string $filtersArgumentName;
    private bool $disableFilterCount;
    private array $propertyOverrides;
    private Request $request;
    private FilterableRepository $repository;
    private ContentObjectRenderer $cObj;

    public function __construct(
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly UriBuilder $uriBuilder,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DatabaseService $databaseService,
        private readonly FlexFormService $flexFormService,
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
        $cType = strtolower($this->extensionName . '_' . $this->pluginName);
        $this->tableName = PluginUtility::getTableName($cType);
        $this->disableFilterCount = PluginUtility::getDisableFilterCount($cType);
        $this->propertyOverrides = $this->getPropertyOverrides();
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
        $event = $this->eventDispatcher->dispatch(new ModifyFilterableListResultEvent($filterableListResult));
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

                /** @var CustomDetailEntitySourceEvent $event */
                $event = $this->eventDispatcher->dispatch(
                    new CustomDetailEntitySourceEvent($this->extensionName, $source, $arguments)
                );
                $item = $event->getResult();
                break;
        }
        $result->setItem($item);

        $properties = $this->getProperties(
            GeneralUtility::trimExplode(',', $this->settings[DetailSheets::PROPERTIES], true)
        );
        $result->setProperties($properties);

        /** @var EnrichDetailResultEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new EnrichDetailResultEvent($result)
        );

        $result = $event->getDetailResult();

        if ($item) {
            $this->addCacheTag($this->tableName . '_' . $item->getUid());
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

        $properties = $this->getProperties(
            GeneralUtility::trimExplode(',', $this->settings[ListSheets::PROPERTIES], true)
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

            if ($disabled || !$filterName) {
                continue;
            }

            $filterValues = [];

            $dynamicValues = (bool) ($filterSetting[FrontendFilterSheets::DYNAMIC_VALUES] ?? false);

            if ($dynamicValues) {
                $fieldNames = GeneralUtility::trimExplode(',', $filterName, true);
                $dynamicFilterValues = $this->databaseService->getAvailableFieldValues(
                    $this->cObj->data['sys_language_uid'],
                    $this->tableName,
                    $fieldNames,
                    $this->cObj->data['pages'],
                    $this->cObj->data['recursive'],
                    $predefinedDatabaseFilters,
                );

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

            $label = $this->getFieldLabel($filterName);

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

    private function getFrontendFilterValueLabel(string $filterName, array $filterValue): string
    {
        $propertyOverrides = $this->propertyOverrides[$filterName] ?? [];
        $valueOverrides = $propertyOverrides[PropertyOverrideSheets::VALUE_OVERRIDES] ?? [];
        $prefix = $propertyOverrides[PropertyOverrideSheets::VALUE_PREFIX] ?? '';
        $suffix = $propertyOverrides[PropertyOverrideSheets::VALUE_SUFFIX] ?? '';
        return $valueOverrides[$filterValue['value']] ?? $prefix . $filterValue['label'] . $suffix;
    }

    /**
     * @param string $filterName
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param array $value
     * @return bool
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
                    return $databaseFilterValue == $value;
                })
            ) > 0;
        }

        return false;
    }

    /**
     * @param string $filterName
     * @param DatabaseFilter[] $queryDatabaseFilters
     * @param array $values
     * @param bool $exclusive
     * @return string
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
            $filterArguments[$key][] = $value ?? '';
        });

        $filterArguments = FilterUtility::simplifyQueryParameters($filterArguments);

        return $this->uriBuilder
            ->reset()
            ->uriFor(null, [$this->filtersArgumentName => $filterArguments]);
    }

    /**
     * @param DatabaseFilter[] $predefinedDatabaseFilters
     * @param DatabaseFilter[] $queryDatabaseFilters
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
            ? clone ($tmpFilters[$filterName])
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
            array_splice($databaseFilterValues, $index, 1);
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

    private function getProperties(array $properties): array
    {
        return array_map(function (string $property) {
            // Overrides currently only work for properties with single fields
            $valueOverrides = $this->propertyOverrides[$property][PropertyOverrideSheets::VALUE_OVERRIDES] ?? [];
            $valueOverrides = array_reduce(
                array_keys($valueOverrides),
                function (array $result, string $jsonValue) use ($valueOverrides) {
                    $value = json_decode($jsonValue, true);
                    if (count($value) === 1) {
                        $label = $valueOverrides[$jsonValue];
                        $key = array_key_first($value);
                        $result[$value[$key]] = $label;
                    }
                    return $result;
                },
                []
            );
            return new Property(
                GeneralUtility::underscoredToLowerCamelCase($property),
                $this->getFieldLabel($property),
                $this->propertyOverrides[$property][PropertyOverrideSheets::VALUE_PREFIX] ?? '',
                $this->propertyOverrides[$property][PropertyOverrideSheets::VALUE_SUFFIX] ?? '',
                $valueOverrides,
            );
        }, $properties);
    }

    private function getPropertyOverrides(): array
    {
        $propertyOverrides = $this->settings[PropertyOverrideSheets::OVERRIDES] ?? [];

        $contentElementId = $this->settings[PropertyOverrideSheets::REFERENCE] ?? null;

        if ($contentElementId) {
            $propertyOverrides = [];
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
            $result = $queryBuilder
                ->select('pi_flexform')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($contentElementId, Connection::PARAM_INT)
                    )
                )
                ->executeQuery();

            $row = $result->fetchOne();
            if ($row) {
                $flexForm = $this->flexFormService->convertFlexFormContentToArray($row);
                $propertyOverrides = $flexForm['settings'][PropertyOverrideSheets::OVERRIDES] ?? [];
            }
        }
        return array_reduce($propertyOverrides, function (array $result, array $property) {
            $property = $property[PropertyOverrideSheets::OVERRIDE];
            $valueOverrides = json_decode($property[PropertyOverrideSheets::VALUE_OVERRIDES], true) ?? [];
            $property[PropertyOverrideSheets::VALUE_OVERRIDES] = array_reduce(
                $valueOverrides,
                function (array $result, array $valueOverride) {
                    $result[$valueOverride['value']] = $valueOverride['label'];
                    return $result;
                },
                []
            );
            $result[$property[PropertyOverrideSheets::FIELDS]] = $property;
            return $result;
        }, []);
    }

    private function getFieldLabel(string $field)
    {
        $label = $this->propertyOverrides[$field][PropertyOverrideSheets::LABEL] ?? null;
        if (!$label) {
            $fields = GeneralUtility::trimExplode(',', $field, true);
            $labels = array_map(function (string $field) {
                $label = BackendUtility::getItemLabel($this->tableName, $field);
                return str_starts_with($label, 'LLL:') ? LocalizationUtility::translate($label) : $label;
            }, $fields);
            $label = implode(', ', $labels);
        }
        return $label;
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
