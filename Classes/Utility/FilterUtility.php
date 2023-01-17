<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\Backend\ItemsProc;
use Remind\Extbase\Domain\Repository\PageRepository;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use TYPO3\CMS\Backend\Utility\BackendUtility as T3BackendUtility;
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
        foreach ($filters as $filterName => $firstLevel) {
            $result[$filterName] = [];
            if (is_array($firstLevel)) {
                $allowMultipleFields = count(array_filter(array_keys($firstLevel), 'is_string')) > 0;
                if ($allowMultipleFields) {
                    $result[$filterName][] = self::getQueryParamFieldValues($filterName, $firstLevel);
                } else {
                    foreach ($firstLevel as $secondLevel) {
                        if (is_array($secondLevel)) {
                            $result[$filterName][] = self::getQueryParamFieldValues($filterName, $secondLevel);
                        } else {
                            $result[$filterName][] = [$filterName => $secondLevel];
                        }
                    }
                }
            } else {
                $result[$filterName] = [[$filterName => $firstLevel]];
            }
        }
        return $result;
    }

    public static function getAvailableValues(array $data, ?array $currentValues = []): array
    {
        $databaseRow = $data['databaseRow'];
        $pages = array_map(function (array $page) {
            return $page['uid'];
        }, $databaseRow['pages']);
        $recursive = (int) $databaseRow['recursive'][0];

        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $pageIds = $pageRepository->getPageIdsRecursive($pages, $recursive);

        $flexFormRowData = $data['flexFormRowData'];
        $allowMultipleFields = (bool) $flexFormRowData[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS]['vDEF'] ?? false;
        $flexFormDataStructureArray = $data['flexFormDataStructureArray'];

        $result = [];

        $fieldsElement = $allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD;

        $tableName = $flexFormDataStructureArray
            [$fieldsElement]
            ['config']
            [ItemsProc::PARAMETERS]
            [ItemsProc::PARAMETER_TABLE_NAME] ?? null;
        $fieldNames = $flexFormRowData[$fieldsElement]['vDEF'];

        if (!empty($fieldNames)) {
            $result = self::getValuesFromFields($fieldNames, $tableName, $pageIds);
        }

        $availablesValues = array_map(function (array $value) {
            return $value[1];
        }, $result);

        $diff = array_diff($currentValues ?? [], $availablesValues);

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
            $fieldTca = T3BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
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
                            $queryBuilder->expr()->eq($mmTable . '.uid_local', $tableName . '.uid')
                        )
                        ->leftJoin(
                            $mmTable,
                            $foreignTable,
                            $foreignTable,
                            $queryBuilder->expr()->eq($mmTable . '.uid_foreign', $foreignTable . '.uid')
                        );
                } else {
                    $queryBuilder
                        ->leftJoin(
                            $tableName,
                            $foreignTable,
                            $foreignTable,
                            $queryBuilder->expr()->eq($tableName . '.' . $fieldName, $foreignTable . '.uid')
                        );
                }
                $foreignTableSelectFields = T3BackendUtility::getCommonSelectFields($foreignTable);
                $foreignTableSelectFields = GeneralUtility::trimExplode(',', $foreignTableSelectFields);
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
            ->where($queryBuilder->expr()->inSet($tableName . '.pid', implode(',', $pageIds)));

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
                        ? T3BackendUtility::getRecordTitle($table, $foreignTableRow)
                        : LocalizationUtility::translate('emptyValue', 'rmnd_extbase');
                    $data['label'][] = $label;
                    $data['value'][$foreignTables[$table]] = $value;
                    unset($data['foreignTableRows']);
                }
            }
            $label = implode(', ', $data['label']);

            if (count($fieldNames) === 1) {
                $value = current($data['value']);
                $value = is_string($value) ? htmlspecialchars($value) : $value;
            } else {
                $value = htmlspecialchars(json_encode($data['value']));
            }

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
        $fieldNames = GeneralUtility::trimExplode(',', $filterName);
        $fieldValues = [];
        foreach ($fieldNames as $fieldName) {
            $fieldValues[$fieldName] = $values[$fieldName];
        }
        return $fieldValues;
    }
}
