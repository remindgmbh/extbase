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

class BackendUtility
{
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

        if ($allowMultipleFields) {
            $tableName = $flexFormDataStructureArray
                [ListFiltersSheets::FIELDS]
                ['config']
                [ItemsProc::PARAMETERS]
                [ItemsProc::PARAMETER_TABLE_NAME] ?? null;
            $fieldNames = $flexFormRowData[ListFiltersSheets::FIELDS]['vDEF'] ?? [];
            if (!empty($fieldNames)) {
                $result = self::getValuesFromFields($fieldNames, $tableName, $pageIds);
            }
        } else {
            $tableName = $flexFormDataStructureArray
                [ListFiltersSheets::FIELD]
                ['config']
                [ItemsProc::PARAMETERS]
                [ItemsProc::PARAMETER_TABLE_NAME] ?? null;
            $fieldName = $flexFormRowData[ListFiltersSheets::FIELD]['vDEF'][0] ?? null;
            if ($fieldName) {
                $fieldTca = T3BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
                $mmTable = $fieldTca['MM'] ?? null;
                $foreignTable = $fieldTca['foreign_table'] ?? null;
                $result = $foreignTable
                    ? self::getValuesFromForeignTable($fieldName, $tableName, $foreignTable, $mmTable, $pageIds)
                    : self::getValuesFromFields([$fieldName], $tableName, $pageIds);
            }
        }


        $availablesValues = array_map(function (array $value) {
            return $value[1];
        }, $result);

        $diff = array_diff($currentValues ?? [], $availablesValues);

        $noMatchingLabel = '[ ' . LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue') . ' ]';

        array_unshift($result, ...array_map(function (string $value) use ($noMatchingLabel) {
            return [
                @sprintf($noMatchingLabel, $value),
                $value,
            ];
        }, $diff));

        return $result;
    }

    protected static function getValuesFromFields(array $fieldNames, string $tableName, array $pageIds): array
    {
        $fieldNames = array_map(function (string $fieldName) {
            return GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
        }, $fieldNames);

        $queryBuilder = self::getQueryBuilder($tableName);

        $queryBuilder
            ->select(...$fieldNames)
            ->from($tableName)
            ->distinct()
            ->where('FIND_IN_SET(`' . $tableName . '`.`pid`, \'' . implode(',', $pageIds) . '\')');

        foreach ($fieldNames as $fieldName) {
            $queryBuilder->orderBy($fieldName);
        }

        $queryResult = $queryBuilder->executeQuery();

        $rows = $queryResult->fetchAllAssociative();

        $normalizedRows = array_map(function (array $row) {
            return array_map(function ($column) {
                return $column ?? '';
            }, $row);
        }, $rows);
        $normalizedJsonRows = array_map(function (array $row) {
            return json_encode($row);
        }, $normalizedRows);

        $uniqueJsonRows = array_unique($normalizedJsonRows);

        $uniqueRows = array_intersect_key($normalizedRows, $uniqueJsonRows);

        return array_map(function (array $row) {
            if (count($row) === 1) {
                $value = current($row);
                return [
                    $value ? $value : LocalizationUtility::translate('emptyValue', 'rmnd_extbase'),
                    is_string($value) ? htmlspecialchars($value) : $value,
                ];
            } else {
                return [
                    implode(', ', array_map(function ($value) {
                        return $value ? $value : LocalizationUtility::translate('emptyValue', 'rmnd_extbase');
                    }, $row)),
                    htmlspecialchars(json_encode($row)),
                ];
            }
        }, $uniqueRows);
    }

    protected static function getValuesFromForeignTable(
        string $fieldName,
        string $tableName,
        string $foreignTable,
        ?string $mmTable,
        array $pageIds
    ): array {
        $fieldList = T3BackendUtility::getCommonSelectFields($foreignTable, $foreignTable . '.');
        $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);

        $queryBuilder = self::getQueryBuilder($foreignTable);

        $queryBuilder
            ->select(...$fieldList)
            ->from($foreignTable)
            ->distinct();

        $quotedIdentifier = $queryBuilder->quoteIdentifier($foreignTable . '.uid');

        if ($mmTable) {
            $queryBuilder
                ->join(
                    $foreignTable,
                    $mmTable,
                    'mm',
                    $queryBuilder->expr()->eq('mm.uid_foreign', $quotedIdentifier)
                )
                ->join(
                    'mm',
                    $tableName,
                    $tableName,
                    $queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->quoteIdentifier($tableName . '.uid'))
                );
        } else {
            $queryBuilder
                ->join(
                    $foreignTable,
                    $tableName,
                    $tableName,
                    $queryBuilder->expr()->eq($tableName . '.' . $fieldName, $quotedIdentifier)
                );
        }

        $queryBuilder->where('FIND_IN_SET(`' . $tableName . '`.`pid`, \'' . implode(',', $pageIds) . '\')');

        $queryResult = $queryBuilder->executeQuery();
        $rows = $queryResult->fetchAllAssociative();

        $result = array_map(function (array $row) use ($foreignTable) {
            $label = T3BackendUtility::getRecordTitle($foreignTable, $row);
            return [$label, $row['uid']];
        }, $rows);

        // count records with no value set
        $queryBuilder = self::getQueryBuilder($tableName);
        $queryBuilder
            ->select($fieldName)
            ->from($tableName)
            ->where($queryBuilder->expr()->or(
                $queryBuilder->expr()->eq($fieldName, 0),
                $queryBuilder->expr()->isNull($fieldName)
            ));

        $count = $queryBuilder->executeQuery()->rowCount();

        if ($count > 0) {
            $result[] = [LocalizationUtility::translate('emptyValue', 'rmnd_extbase'), ''];
        }

        sort($result);

        return $result;
    }

    protected static function getQueryBuilder(string $tableName): QueryBuilder
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
    protected static function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
