<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\Domain\Repository\PageRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility as T3BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class BackendUtility
{
    public static function getAvailableValues(
        string $tableName,
        string $fieldName,
        ?array $pageIds = [],
        ?int $recursive = 0
    ): array {
        $fieldTca = T3BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
        $mmTable = $fieldTca['MM'] ?? null;
        $foreignTable = $fieldTca['foreign_table'] ?? null;
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $pageIds = $pageRepository->getPageIdsRecursive($pageIds, $recursive);

        return $foreignTable
            ? self::getValuesFromForeignTable($fieldName, $tableName, $foreignTable, $mmTable, $pageIds)
            : self::getValuesFromField($fieldName, $tableName, $pageIds);
    }

    protected static function getValuesFromField(string $fieldName, string $tableName, array $pageIds): array
    {
        $fieldName = GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);

        $queryBuilder = self::getQueryBuilder($tableName);

        $queryBuilder
            ->select($fieldName)
            ->from($tableName)
            ->distinct()
            ->orderBy($fieldName)
            ->where('FIND_IN_SET(`' . $tableName . '`.`pid`, \'' . implode(',', $pageIds) . '\')');

        $queryResult = $queryBuilder->executeQuery();

        $values = array_values(array_unique(array_map(function ($value) {
            return $value ?? '';
        }, $queryResult->fetchFirstColumn())));

        return array_map(function ($value) {
            $result = ['value' => $value];
            if (!$value) {
                $result['label'] = LocalizationUtility::translate('emptyValue', 'rmnd_extbase');
            }
            return $result;
        }, $values);
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
            return [
                'value' => $row['uid'],
                'label' => $label,
            ];
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
            $result[] = [
                'label' => LocalizationUtility::translate('emptyValue', 'rmnd_extbase'),
                'value' => '',
            ];
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
