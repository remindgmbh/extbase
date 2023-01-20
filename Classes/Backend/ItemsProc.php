<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend;

use Remind\Extbase\Domain\Repository\PageRepository;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\Utility\FilterUtility;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
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

    public function __construct()
    {
        $this->pageRepository = GeneralUtility::makeInstance(PageRepository::class);
    }

    public function getDetailSources(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $extensionName = $flexParentDatabaseRow[PluginUtility::COLUMN_EXTENSION_NAME];
        $sources = PluginUtility::getDetailSources($extensionName);
        foreach ($sources as $pluginSignature => $config) {
            $params['items'][] = [$config['label'] ?? $pluginSignature, $pluginSignature];
        }
    }

    public function getRecordsInPages(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $tableName = $flexParentDatabaseRow[PluginUtility::COLUMN_TABLE_NAME];
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $pages = $flexParentDatabaseRow['pages'];
        $pageIds = GeneralUtility::intExplode(',', $pages, true);
        $recursive = $flexParentDatabaseRow['recursive'];
        $pageIds = $this->pageRepository->getPageIdsRecursive($pageIds, $recursive);

        if (count($pageIds) > 0) {
            $fieldList = BackendUtility::getCommonSelectFields($tableName, $tableName . '.');
            $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($tableName);

            $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
                ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace));

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
                $params['items'][] = [$title, $row['uid']];
            }
        }
    }

    public function getFilterFields(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $tableName = $flexParentDatabaseRow[PluginUtility::COLUMN_TABLE_NAME];
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

        $filters = $params
            ['flexParentDatabaseRow']
            ['pi_flexform']['data']
            [ListFiltersSheets::SHEET_ID]
            ['lDEF']
            ['settings.' . ListFiltersSheets::FILTERS]
            ['el'];

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
            $filter = $filter[ListFiltersSheets::FILTER]['el'];
            $allowMultipleFields = (bool) $filter[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS]['vDEF'];
            if ($allowMultipleFields) {
                $fields = $filter[ListFiltersSheets::FIELDS]['vDEF'] ?? [];
                $fields = is_array($fields) ? $fields : GeneralUtility::trimExplode(',', $fields, true);
                foreach ($fields as $field) {
                    if (!in_array($field, $currentFields)) {
                        $result[] = $field;
                    }
                }
            } else {
                $field = $filter[ListFiltersSheets::FIELD]['vDEF'];
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
            return [$label, $field];
        }, $fields);

        $params['items'] = array_merge(
            $params['items'],
            $notMatchingFields,
            $fields
        );
    }

    public function getFilterValues(array &$params): void
    {
        $row = $params['row'];
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $tableName = $flexParentDatabaseRow[PluginUtility::COLUMN_TABLE_NAME];
        $currentValues = GeneralUtility::trimExplode(',', $row[$params['field']], true);

        if ($params['field'] === ListFiltersSheets::AVAILABLE_VALUES) {
            $currentValues = array_map(function (string $base64Value) {
                $value = json_decode(base64_decode($base64Value), true);
                return base64_encode(json_encode($value['value']));
            }, $currentValues);
        }

        $items = FilterUtility::getAvailableValues($tableName, $row, $flexParentDatabaseRow, $currentValues);
        $params['items'] = array_merge($params['items'], $items);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
