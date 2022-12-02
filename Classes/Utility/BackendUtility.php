<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility as T3BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUtility
{
    public static function getAvailableValues(string $tableName, string $fieldName): array
    {
        $fieldTca = T3BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
        $mmTable = $fieldTca['MM'] ?? null;
        $foreignTable = $fieldTca['foreign_table'] ?? null;

        return $foreignTable
            ? self::getValuesFromForeignTable($fieldName, $tableName, $foreignTable, $mmTable)
            : self::getValuesFromField($fieldName, $tableName);
    }

    protected static function getValuesFromField(string $fieldName, string $tableName): array
    {
        $fieldName = GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($tableName);

        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, self::getBackendUser()->workspace));

        $queryBuilder
            ->select($fieldName)
            ->from($tableName)
            ->distinct()
            ->where($queryBuilder->expr()->isNotNull($fieldName));

        $queryResult = $queryBuilder->executeQuery();

        return array_map(function ($value) {
            return [
                'value' => $value,
            ];
        }, $queryResult->fetchFirstColumn());
    }

    protected static function getValuesFromForeignTable(
        string $fieldName,
        string $tableName,
        string $foreignTable,
        ?string $mmTable
    ): array {
        $fieldList = T3BackendUtility::getCommonSelectFields($foreignTable, $foreignTable . '.');
        $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($foreignTable);

        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, self::getBackendUser()->workspace));

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
                );
        } else {
            $queryBuilder
                ->join(
                    $foreignTable,
                    $tableName,
                    'table',
                    $queryBuilder->expr()->eq('table.' . $fieldName, $quotedIdentifier)
                );
        }

        $queryResult = $queryBuilder->executeQuery();
        $rows = $queryResult->fetchAllAssociative();

        return array_map(function (array $row) use ($foreignTable) {
            $label = T3BackendUtility::getRecordTitle($foreignTable, $row);
            return [
                'value' => $row['uid'],
                'label' => $label,
            ];
        }, $rows);
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
