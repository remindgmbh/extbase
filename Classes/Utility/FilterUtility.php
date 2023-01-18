<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\Domain\Repository\PageRepository;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FilterUtility
{
    public static function normalizeQueryParameters(array $filters): array
    {
        $result = [];
        foreach ($filters as $fieldName => $values) {
            $result[$fieldName] = is_array($values) ? $values : [$values];
        }
        return $result;
    }

    public static function getAvailableValues(string $tableName, array $row, array $flexParentDatabaseRow, array $currentValues = []): array
    {
        $pages = $flexParentDatabaseRow['pages'];
        $recursive = $flexParentDatabaseRow['recursive'];
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $pageIds = GeneralUtility::trimExplode(',', $pages, true);
        $pageIds = $pageRepository->getPageIdsRecursive($pageIds, $recursive);

        $allowMultipleFields = (bool) $row[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS] ?? false;

        $result = [];

        $fieldsElement = $allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD;
        $fieldNames = $row[$fieldsElement];
        if (!is_array($fieldNames)) {
            $fieldNames = GeneralUtility::trimExplode(',', $fieldNames, true);
        }

        if (!empty($fieldNames)) {
            $result = self::getValuesFromFields($fieldNames, $tableName, $pageIds);
        }

        $availablesValues = array_map(function (array $value) {
            return $value[1];
        }, $result);

        $diff = array_diff($currentValues, $availablesValues);

        $noMatchingLabel = sprintf(
            '[ %s ]',
            LocalizationUtility::translate(
                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'
            )
        );

        // Add "INVALID VALUE" for every selected value not present in available values
        array_unshift(
            $result,
            ...array_map(function (string $value) use ($noMatchingLabel) {
                return [
                    sprintf($noMatchingLabel, $value),
                    $value,
                ];
            }, $diff)
        );

        return $result;
    }

    protected static function getValuesFromFields(array $fieldNames, string $tableName, array $pageIds): array
    {
        $fieldNames = array_map(function (string $fieldName) {
            return GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
        }, $fieldNames);

        $queryBuilder = self::getQueryBuilder($tableName);

        $selectFields = [];
        $foreignTables = [];

        foreach ($fieldNames as $fieldName) {
            $fieldTca = BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
            $mmTable = $fieldTca['MM'] ?? null;
            $foreignTable = $fieldTca['foreign_table'] ?? null;

            if ($foreignTable) {
                $foreignTables[$foreignTable] = $fieldName;
                if ($mmTable) {
                    $queryBuilder
                        ->leftJoin(
                            $tableName,
                            $mmTable,
                            $mmTable,
                            $queryBuilder->expr()->eq(
                                $mmTable . '.uid_local',
                                $queryBuilder->quoteIdentifier($tableName . '.uid')
                            )
                        )
                        ->leftJoin(
                            $mmTable,
                            $foreignTable,
                            $foreignTable,
                            $queryBuilder->expr()->eq(
                                $mmTable . '.uid_foreign',
                                $queryBuilder->quoteIdentifier($foreignTable . '.uid')
                            )
                        );
                } else {
                    $queryBuilder
                        ->leftJoin(
                            $tableName,
                            $foreignTable,
                            $foreignTable,
                            $queryBuilder->expr()->eq(
                                $tableName . '.' . $fieldName,
                                $queryBuilder->quoteIdentifier($foreignTable . '.uid')
                            )
                        );
                }
                $foreignTableSelectFields = BackendUtility::getCommonSelectFields($foreignTable);
                $foreignTableSelectFields = GeneralUtility::trimExplode(',', $foreignTableSelectFields, true);
                $foreignTableSelectFields = array_map(function (string $field) use ($foreignTable) {
                    return self::formatSelectField($field, $foreignTable);
                }, $foreignTableSelectFields);
                array_push($selectFields, ...$foreignTableSelectFields);
            } else {
                $selectFields[] = self::formatSelectField($fieldName, $tableName);
            }
        }

        $queryBuilder
            ->select(...$selectFields)
            ->from($tableName, $tableName)
            ->distinct()
            ->where($queryBuilder->expr()->in(
                $tableName . '.pid',
                $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
            ));

        $queryResult = $queryBuilder->executeQuery();

        $rows = $queryResult->fetchAllAssociative();

        $result = array_map(function (array $row) use ($tableName, $foreignTables, $fieldNames) {
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
            $value = base64_encode(json_encode($data['value']));

            return [$label, $value];
        }, $rows);

        return array_unique($result, SORT_REGULAR);
    }

    private static function formatSelectField(string $fieldName, string $tableName): string
    {
        return sprintf('%s.%s AS %s_%s', $tableName, $fieldName, $tableName, $fieldName);
    }

    private static function getQueryBuilder(string $tableName): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($tableName);

        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, self::getBackendUser()->workspace));
        return $queryBuilder;
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    private static function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    private static function getQueryParamFieldValues(string $filterName, array $values): array
    {
        $fieldNames = GeneralUtility::trimExplode(',', $filterName, true);
        $fieldValues = [];
        foreach ($fieldNames as $fieldName) {
            $fieldValues[$fieldName] = $values[$fieldName];
        }
        return $fieldValues;
    }
}
