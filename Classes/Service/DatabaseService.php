<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Extbase\Utility\Dto\Conjunction;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DatabaseService
{

    private PageRepository $pageRepository;

    public function __construct()
    {
        $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
    }

    public function getRecords(string $tableName, string $pages, ?int $recursive = 0): array
    {
        $result = [];

        $pageIds = $this->getPageIds($pages, $recursive);

        if (empty($pageIds)) {
            return $result;
        }

        $queryBuilder = $this->getQueryBuilder($tableName);

        $fieldList = BackendUtility::getCommonSelectFields($tableName, $tableName . '.');
        $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);

        $queryBuilder
            ->select(...$fieldList)
            ->from($tableName)
            ->where($queryBuilder->expr()->in(
                $tableName . '.pid',
                $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
            ));

        $queryResult = $queryBuilder->executeQuery();
        $rows = $queryResult->fetchAllAssociative();

        foreach ($rows as $row) {
            $title = BackendUtility::getRecordTitle($tableName, $row);
            $result[] = ['label' => $title, 'value' => $row['uid']];
        }

        return $resault;
    }

    /**
     * @param \Remind\Extbase\Utility\Dto\DatabaseFilter[] $filters
     */
    public function getAvailableFieldValues(
        string $tableName,
        array $fieldNames,
        string $pages,
        ?int $recursive = 0,
        ?array $filters = [],
    ): array {
        $queryBuilder = $this->getQueryBuilder($tableName);
        $result = [];

        if (empty($fieldNames)) {
            return $result;
        }

        $pageIds = $this->getPageIds($pages, $recursive);

        $fieldNames = array_map(function (string $fieldName) {
            return GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
        }, $fieldNames);

        $selectFields = [];
        $foreignTables = [];

        foreach ($fieldNames as $fieldName) {
            $fieldTca = BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
            $mmTable = $fieldTca['MM'] ?? null;
            $foreignTable = $fieldTca['foreign_table'] ?? null;

            if ($foreignTable) {
                $foreignTables[$foreignTable] = $fieldName;
                $queryBuilder = $this->addQueryBuilderJoins(
                    $queryBuilder,
                    $tableName,
                    $foreignTable,
                    $fieldName,
                    $mmTable
                );
                $foreignTableSelectFields = BackendUtility::getCommonSelectFields($foreignTable);
                $foreignTableSelectFields = GeneralUtility::trimExplode(',', $foreignTableSelectFields, true);
                $foreignTableSelectFields = array_map(function (string $field) use ($foreignTable) {
                    return $this->formatSelectField($field, $foreignTable);
                }, $foreignTableSelectFields);
                array_push($selectFields, ...$foreignTableSelectFields);
            } else {
                $selectFields[] = $this->formatSelectField($fieldName, $tableName);
            }
        }

        $constraints = $this->getConstraint($queryBuilder, $tableName, $filters);

        $queryBuilder
            ->select(...$selectFields)
            ->from($tableName)
            ->distinct()
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->in(
                        $tableName . '.pid',
                        $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
                    ),
                    $constraints,
                )
            );

        $queryResult = $queryBuilder->executeQuery();

        $rows = $queryResult->fetchAllAssociative();

        $result = $this->formatFilterValues($rows, $tableName, $foreignTables);

        // Sort entries in $result by label
        usort($result, function (array $a, array $b) {
            return strnatcmp($a['label'], $b['label']);
        });

        $result = array_unique($result, SORT_REGULAR);

        return $result;
    }

    /**
     * @param \Remind\Extbase\Utility\Dto\DatabaseFilter[] $filters
     */
    private function getConstraint(
        QueryBuilder $queryBuilder,
        string $tableName,
        array $filters,
    ): ?CompositeExpression {
        $constraints = [];

        foreach ($filters as $filter) {
            $filterConstraints = [];
            foreach ($filter->getValues() as $key => $filterValue) {
                $valueConstraints = [];
                foreach ($filterValue as $fieldName => $value) {
                    $fieldTca = BackendUtility::getTcaFieldConfiguration($tableName, $fieldName);
                    $mmTable = $fieldTca['MM'] ?? null;
                    $foreignTable = $fieldTca['foreign_table'] ?? null;

                    if ($foreignTable) {
                        $queryBuilder = $this->addQueryBuilderJoins(
                            $queryBuilder,
                            $tableName,
                            $foreignTable,
                            $fieldName,
                            $mmTable,
                            $key,
                        );
                        $valueConstraints[] = $queryBuilder->expr()->eq(
                            $key . $foreignTable . '.uid',
                            $queryBuilder->createNamedParameter($value)
                        );
                    } else {
                        $valueConstraints[] = $queryBuilder->expr()->eq(
                            $fieldName,
                            $queryBuilder->createNamedParameter($value)
                        );
                    }
                }
                return $queryBuilder->expr()->and(...$valueConstraints);
            }

            $constraints[] = ($filter->getConjunction() === Conjunction::AND->value)
                ? $queryBuilder->expr()->and(...$filterConstraints)
                : $queryBuilder->expr()->or(...$filterConstraints);
        }
        return $queryBuilder->expr()->and(...$constraints);
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

    private function formatSelectField(string $fieldName, string $tableName): string
    {
        return sprintf('%s.%s AS %s_%s', $tableName, $fieldName, $tableName, $fieldName);
    }

    private function getPageIds(string $pages, int $recursive): array
    {
        $pageIds = GeneralUtility::intExplode(',', $pages, true);
        return $this->pageRepository->getPageIdsRecursive($pageIds, $recursive);
    }

    private function addQueryBuilderJoins(
        QueryBuilder $queryBuilder,
        string $tableName,
        string $foreignTable,
        string $fieldName,
        ?string $mmTable,
        mixed $aliasPrefix = '',
    ): QueryBuilder {
        if ($mmTable) {
            $aliasPrefix = strval($aliasPrefix);
            $queryBuilder
                ->leftJoin(
                    $tableName,
                    $mmTable,
                    $aliasPrefix . $mmTable,
                    $queryBuilder->expr()->eq(
                        $aliasPrefix . $mmTable . '.uid_local',
                        $queryBuilder->quoteIdentifier($tableName . '.uid')
                    )
                )
                ->leftJoin(
                    $aliasPrefix . $mmTable,
                    $foreignTable,
                    $aliasPrefix . $foreignTable,
                    $queryBuilder->expr()->eq(
                        $aliasPrefix . $mmTable . '.uid_foreign',
                        $queryBuilder->quoteIdentifier($aliasPrefix . $foreignTable . '.uid')
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
        return $queryBuilder;
    }

    private function getQueryBuilder(string $tableName): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($tableName);

        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()?->workspace ?? 0));

        return $queryBuilder;
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
