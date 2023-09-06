<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend;

use Remind\Extbase\Domain\Repository\Dto\Conjunction;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ItemsProc
{
    private const EXCLUDED_FILTER_FIELDS = [
        'uid',
        'pid',
        'sys_language_uid',
        'l10n_parent',
        'hidden',
        'tstamp',
        'crdate',
        'sorting',
        't3ver_state',
        't3ver_wsid',
        't3ver_oid',
    ];

    private ?PageRepository $pageRepository = null;
    private ?FlexFormService $flexFormService = null;
    private ?string $tableName = null;
    private ?QueryBuilder $queryBuilder = null;

    public function __construct()
    {
        $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
    }

    public function getDetailSources(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $sources = PluginUtility::getDetailSources($flexParentDatabaseRow['CType']);
        foreach ($sources as $source) {
            $params['items'][] = ['label' => $source['label'], 'value' => $source['value']];
        }
    }

    public function getRecordsInPages(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $pages = $flexParentDatabaseRow['pages'];
        $pageIds = GeneralUtility::intExplode(',', $pages, true);
        $recursive = (int) $flexParentDatabaseRow['recursive'];
        $pageIds = $this->pageRepository->getPageIdsRecursive($pageIds, $recursive);

        if (count($pageIds) > 0) {
            $this->initQueryBuilder($params);

            $fieldList = BackendUtility::getCommonSelectFields($this->tableName, $this->tableName . '.');
            $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);

            $this->queryBuilder
                ->select(...$fieldList)
                ->from($this->tableName)
                ->where($this->queryBuilder->expr()->in(
                    $this->tableName . '.pid',
                    $this->queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
                ));

            $queryResult = $this->queryBuilder->executeQuery();
            $rows = $queryResult->fetchAllAssociative();

            foreach ($rows as $row) {
                $title = BackendUtility::getRecordTitle($this->tableName, $row);
                $params['items'][] = ['label' => $title, 'value' => $row['uid']];
            }
        }
    }

    public function getSelectedFilterFields(array &$params): void
    {
        $row = $params['row'];
        $allowMultipleFields = (bool) $row[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
        $fields = $allowMultipleFields
            ? GeneralUtility::trimExplode(',', $row[ListFiltersSheets::FIELDS], true)
            : [$row[ListFiltersSheets::FIELD]];
        $params['items'] = array_map(function (string $field) {
            return ['label' => $field, 'value' => $field];
        }, $fields);
    }

    public function getFilterFields(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $tableName = PluginUtility::getTableName($flexParentDatabaseRow['CType']);
        $fields = BackendUtility::getAllowedFieldsForTable($tableName);
        $fields = array_diff($fields, self::EXCLUDED_FILTER_FIELDS);
        $row = $params['row'];
        $allowMultipleFields = (bool) $row[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];

        if (
            !$allowMultipleFields && $params['field'] === ListFiltersSheets::FIELDS ||
            $allowMultipleFields && $params['field'] === ListFiltersSheets::FIELD
        ) {
            return;
        }

        $filters = $this->getFilterDefinitions($params);

        if ($allowMultipleFields) {
            $currentFields = GeneralUtility::trimExplode(',', $row[ListFiltersSheets::FIELDS], true);
        } else {
            $currentFields = [];
            $field = $row[ListFiltersSheets::FIELD];
            if ($field) {
                $currentFields[] = $field;
            }
        }

        $usedFields = array_reduce($filters, function (array $result, array $filter) use ($currentFields) {
            $allowMultipleFields = (bool) $filter[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
            if ($allowMultipleFields) {
                $fields = $filter[ListFiltersSheets::FIELDS] ?? [];
                $fields = is_array($fields) ? $fields : GeneralUtility::trimExplode(',', $fields, true);
                foreach ($fields as $field) {
                    if (!in_array($field, $currentFields)) {
                        $result[] = $field;
                    }
                }
            } else {
                $field = $filter[ListFiltersSheets::FIELD];
                $field = is_array($field) ? current($field) : $field;
                if (!in_array($field, $currentFields)) {
                    $result[] = $field;
                }
            }

            return $result;
        }, []);

        $fields = array_diff($fields, $usedFields);

        $noMatchingLabel = sprintf(
            '[ %s ]',
            LocalizationUtility::translate(
                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'
            )
        );

        $notMatchingFields = array_diff($currentFields, $fields);
        $notMatchingFields = array_map(function (string $field) use ($noMatchingLabel) {
            return [sprintf($noMatchingLabel, $field), $field];
        }, $notMatchingFields);

        $fields = array_map(function (string $field) use ($tableName) {
            $label = BackendUtility::getItemLabel($tableName, $field);
            return ['label' => $label, 'value' => $field];
        }, $fields);

        $params['items'] = array_merge(
            $params['items'],
            $notMatchingFields,
            $fields
        );
    }

    public function getAppliedFilterValues(array &$params): void
    {
        $this->initQueryBuilder($params);
        $currentValues = $this->getCurrentFilterValues($params);
        $items = $this->getFilterValues($params);
        $this->addInvalidValues($items, $currentValues);
        $params['items'] = array_merge($params['items'], $items);
    }

    public function getAvailableFilterValues(array $params): void
    {
        $this->initQueryBuilder($params);

        $constraints = $this->getAvailableFilterConstraints($params);

        $items = $this->getFilterValues($params, $constraints);
        $params['items'] = array_merge($params['items'], $items);
    }

    private function getFilterValues(
        array $params,
        ?CompositeExpression $constraints = null,
    ): array {
        $row = $params['row'];
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];

        $pages = $flexParentDatabaseRow['pages'];
        $recursive = $flexParentDatabaseRow['recursive'];
        $pageIds = GeneralUtility::trimExplode(',', $pages, true);
        $pageIds = $this->pageRepository->getPageIdsRecursive($pageIds, $recursive);

        $fieldNames = $this->getFilterDefinitionFieldNames($row);

        $result = [];

        if (!empty($fieldNames)) {
            $fieldNames = array_map(function (string $fieldName) {
                return GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
            }, $fieldNames);

            $selectFields = [];
            $foreignTables = [];

            foreach ($fieldNames as $fieldName) {
                $fieldTca = BackendUtility::getTcaFieldConfiguration($this->tableName, $fieldName);
                $mmTable = $fieldTca['MM'] ?? null;
                $foreignTable = $fieldTca['foreign_table'] ?? null;

                if ($foreignTable) {
                    $foreignTables[$foreignTable] = $fieldName;
                    $this->addQueryBuilderJoins($foreignTable, $fieldName, $mmTable);
                    $foreignTableSelectFields = BackendUtility::getCommonSelectFields($foreignTable);
                    $foreignTableSelectFields = GeneralUtility::trimExplode(',', $foreignTableSelectFields, true);
                    $foreignTableSelectFields = array_map(function (string $field) use ($foreignTable) {
                        return $this->formatSelectField($field, $foreignTable);
                    }, $foreignTableSelectFields);
                    array_push($selectFields, ...$foreignTableSelectFields);
                } else {
                    $selectFields[] = $this->formatSelectField($fieldName, $this->tableName);
                }
            }

            $this->queryBuilder
                ->select(...$selectFields)
                ->from($this->tableName)
                ->distinct()
                ->where(
                    $this->queryBuilder->expr()->and(
                        $this->queryBuilder->expr()->in(
                            $this->tableName . '.pid',
                            $this->queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
                        ),
                        $constraints,
                    )
                );

            $queryResult = $this->queryBuilder->executeQuery();

            $rows = $queryResult->fetchAllAssociative();

            $result = $this->formatFilterValues($rows, $this->tableName, $foreignTables);

            // Sort entries in $result by label
            usort($result, function (array $a, array $b) {
                return strnatcmp($a['label'], $b['label']);
            });

            $result = array_unique($result, SORT_REGULAR);
        }

        return $result;
    }

    private function addQueryBuilderJoins(
        string $foreignTable,
        string $fieldName,
        ?string $mmTable,
        mixed $aliasPrefix = '',
    ): void {
        if ($mmTable) {
            $aliasPrefix = strval($aliasPrefix);
            $this->queryBuilder
                ->leftJoin(
                    $this->tableName,
                    $mmTable,
                    $aliasPrefix . $mmTable,
                    $this->queryBuilder->expr()->eq(
                        $aliasPrefix . $mmTable . '.uid_local',
                        $this->queryBuilder->quoteIdentifier($this->tableName . '.uid')
                    )
                )
                ->leftJoin(
                    $aliasPrefix . $mmTable,
                    $foreignTable,
                    $aliasPrefix . $foreignTable,
                    $this->queryBuilder->expr()->eq(
                        $aliasPrefix . $mmTable . '.uid_foreign',
                        $this->queryBuilder->quoteIdentifier($aliasPrefix . $foreignTable . '.uid')
                    )
                );
        } else {
            $this->queryBuilder
                ->leftJoin(
                    $this->tableName,
                    $foreignTable,
                    $foreignTable,
                    $this->queryBuilder->expr()->eq(
                        $this->tableName . '.' . $fieldName,
                        $this->queryBuilder->quoteIdentifier($foreignTable . '.uid')
                    )
                );
        }
    }

    private function getCurrentFilterValues(array $params): array
    {
        return json_decode($params['row'][$params['field']], true) ?? [];
    }

    private function getAvailableFilterConstraints(
        array $params,
    ): ?CompositeExpression {
        $filters = $this->getFilterDefinitions($params);

        $constraints = [];

        foreach ($filters as $filter) {
            $disabled = (bool) ($filter[ListFiltersSheets::DISABLED] ?? false);
            if ($disabled) {
                continue;
            }

            $appliedValues = $filter[ListFiltersSheets::APPLIED_VALUES];
            $appliedValues = array_map(function (string $value) {
                return json_decode($value, true);
            }, json_decode($appliedValues, true) ?? []);

            $filterConstraints = [];
            foreach ($appliedValues as $key => $appliedValue) {
                $valueConstraints = [];
                foreach ($appliedValue as $fieldName => $value) {
                    $fieldTca = BackendUtility::getTcaFieldConfiguration($this->tableName, $fieldName);
                    $mmTable = $fieldTca['MM'] ?? null;
                    $foreignTable = $fieldTca['foreign_table'] ?? null;

                    if ($foreignTable) {
                        $this->addQueryBuilderJoins(
                            $foreignTable,
                            $fieldName,
                            $mmTable,
                            $key,
                        );
                        $valueConstraints[] = $this->queryBuilder->expr()->eq(
                            $key . $foreignTable . '.uid',
                            $this->queryBuilder->createNamedParameter($value)
                        );
                    } else {
                        $valueConstraints[] = $this->queryBuilder->expr()->eq(
                            $fieldName,
                            $this->queryBuilder->createNamedParameter($value)
                        );
                    }
                }
                $filterConstraints[] = $this->queryBuilder->expr()->and(...$valueConstraints);
            }
            $conjunction = $filter[ListFiltersSheets::CONJUNCTION];

            $constraints[] = ($conjunction === Conjunction::AND->value)
                ? $this->queryBuilder->expr()->and(...$filterConstraints)
                : $this->queryBuilder->expr()->or(...$filterConstraints);
        }
        return $this->queryBuilder->expr()->and(...$constraints);
    }

    private function formatFilterValues(array $rows, string $tableName, array $foreignTables): array
    {
        return array_map(function (array $row) use ($tableName, $foreignTables) {
            $data = [];
            foreach ([$tableName, ...array_keys($foreignTables)] as $table) {
                foreach ($row as $key => $value) {
                    if (str_starts_with($key, $table)) {
                        $fieldName = str_replace($table . '_', '', $key);
                        if (array_key_exists($table, $foreignTables)) {
                            $data['foreignTableRows'][$table][$fieldName] = $value;
                        } else {
                            $data['label'][] = $value
                                ? $value
                                : LocalizationUtility::translate('emptyValue', 'rmnd_extbase');
                            $data['value'][$fieldName] = $value ?? '';
                        }
                    }
                }
                if (array_key_exists($table, $foreignTables)) {
                    $foreignTableRow = $data['foreignTableRows'][$table];
                    $value = $foreignTableRow['uid'] ?? '';
                    $label = $value
                        ? BackendUtility::getRecordTitle($table, $foreignTableRow)
                        : LocalizationUtility::translate('emptyValue', 'rmnd_extbase');
                    $data['label'][] = $label;
                    $data['value'][$foreignTables[$table]] = $value;
                    unset($data['foreignTableRows']);
                }
            }

            $label = implode(', ', $data['label']);
            $value = json_encode($data['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['label' => $label, 'value' => $value];
        }, $rows);
    }

    private function addInvalidValues(array &$result, array $currentValues): void
    {
        $values = array_map(function (array $value) {
            return $value['value'];
        }, $result);

        $diff = array_diff($currentValues, $values);

        $noMatchingLabel = sprintf(
            '[ %s ]',
            LocalizationUtility::translate(
                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'
            )
        );

        // Add "INVALID VALUE" for every selected value not present in possible values
        array_unshift(
            $result,
            ...array_map(function (string $value) use ($noMatchingLabel) {
                return [
                    // TODO: show label if available
                    sprintf($noMatchingLabel, $value),
                    $value,
                ];
            }, $diff)
        );
    }

    private function getFilterDefinitions(array $params): array
    {
        $flexForm = $this->flexFormService->walkFlexFormNode($params['flexParentDatabaseRow']['pi_flexform']);
        $filters = $flexForm['data'][ListFiltersSheets::SHEET_ID]['lDEF']['settings'][ListFiltersSheets::FILTERS];
        return array_map(function (array $filter) {
            return $filter[ListFiltersSheets::FILTER];
        }, $filters);
    }

    private function getFilterDefinitionFieldNames(array $filter): array
    {
        $allowMultipleFields = (bool) $filter[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS] ?? false;
        $fieldsElement = $allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD;
        $fieldNames = $filter[$fieldsElement];
        if (!is_array($fieldNames)) {
            $fieldNames = GeneralUtility::trimExplode(',', $fieldNames, true);
        }
        return $fieldNames;
    }

    private function formatSelectField(string $fieldName, string $tableName): string
    {
        return sprintf('%s.%s AS %s_%s', $tableName, $fieldName, $tableName, $fieldName);
    }

    private function initQueryBuilder(array $params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $this->tableName = PluginUtility::getTableName($flexParentDatabaseRow['CType']);
        $this->queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->tableName);

        $this->queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace));
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
    
    public function getListOrderBy(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $orderByItems = PluginUtility::getListOrderBy($flexParentDatabaseRow['CType']);
        foreach ($orderByItems as $item) {
            $params['items'][] = ['label' => $item['label'], 'value' => $item['value']];
        }
    }
}
