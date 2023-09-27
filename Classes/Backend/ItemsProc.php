<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend;

use Remind\Extbase\FlexForms\DetailDataSheets;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\Service\DatabaseService;
use Remind\Extbase\Utility\FilterUtility;
use Remind\Extbase\Utility\PluginUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ItemsProc
{
    private const DEFAULT_PROPERTIES = [
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

    private ?FlexFormService $flexFormService = null;
    private ?DatabaseService $databaseService = null;

    public function __construct()
    {
        $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $this->databaseService = GeneralUtility::makeInstance(DatabaseService::class);
    }

    public function getListOrderByItems(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $orderByItems = PluginUtility::getListOrderBy($flexParentDatabaseRow['CType']);
        foreach ($orderByItems as $item) {
            $params['items'][] = ['label' => $item['label'], 'value' => $item['value']];
        }
    }

    public function getDetailDataSourceItems(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $sources = PluginUtility::getDetailSources($flexParentDatabaseRow['CType']);
        foreach ($sources as $source) {
            $params['items'][] = ['label' => $source['label'], 'value' => $source['value']];
        }
    }

    public function getDetailDataRecordItems(array &$params): void
    {
        array_push($params['items'], ...$this->getRecordsInPages($params));
    }

    public function getDetailDataPropertyFieldItems(array $params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $cType = $flexParentDatabaseRow['CType'];
        $row = $params['row'];
        $settings = $this->getSettings($params, DetailDataSheets::SHEET_ID);

        $currentFields = array_filter([$row[DetailDataSheets::FIELD]]);

        $properties = $settings[DetailDataSheets::PROPERTIES];
        $properties = array_map(function (array $property) {
            return $property[DetailDataSheets::PROPERTY];
        }, $properties);

        $usedFields = array_reduce($properties, function (array $result, array $property) use ($currentFields) {
            $field = $property[DetailDataSheets::FIELD];
            $field = is_array($field) ? current($field) : $field;
            if (!in_array($field, $currentFields)) {
                $result[] = $field;
            }
            return $result;
        }, []);

        $properties = $this->getModelProperties($cType, $currentFields, $usedFields);

        array_push($params['items'], ...$properties);
    }

    public function getSelectionDataRecordsItems(array &$params): void
    {
        array_push($params['items'], ...$this->getRecordsInPages($params));
    }

    public function getListFiltersFieldItems(array $params): void
    {
        array_push($params['items'], ...$this->getFilterFields($params));
    }

    public function getListFiltersFieldsItems(array $params): void
    {
        array_push($params['items'], ...$this->getFilterFields($params));
    }

    public function getListFiltersAppliedValuesItems(array &$params): void
    {
        $currentValues = $this->getCurrentFilterValues($params);
        $items = $this->databaseService->getAvailableFieldValues(
            $this->getTableName($params),
            $this->getFieldNames($params),
            $this->getPages($params),
            $this->getRecursive($params),
        );
        $this->addInvalidValues($items, $currentValues);
        array_push($params['items'], ...$items);
    }

    public function getListFiltersAvailableValuesItems(array &$params): void
    {
        $tableName = $this->getTableName($params);

        $filters = FilterUtility::getAppliedValuesDatabaseFilters(
            $this->getSettings($params, ListFiltersSheets::SHEET_ID),
            $tableName,
        );

        $items = $this->databaseService->getAvailableFieldValues(
            $tableName,
            $this->getFieldNames($params),
            $this->getPages($params),
            $this->getRecursive($params),
            $filters,
        );

        array_push($params['items'], ...$items);
    }

    public function getListFiltersAvailableValuesItemProps(array &$params): void
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

    private function getRecordsInPages(array &$params): array
    {
        $pages = $this->getPages($params);
        $recursive = $this->getRecursive($params);
        $tableName = $this->getTableName($params);

        return $this->databaseService->getRecords($tableName, $pages, $recursive);
    }

    private function getFilterFields(array &$params): array
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $cType = $flexParentDatabaseRow['CType'];
        $row = $params['row'];
        $allowMultipleFields = (bool) $row[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];

        if (
            !$allowMultipleFields && $params['field'] === ListFiltersSheets::FIELDS ||
            $allowMultipleFields && $params['field'] === ListFiltersSheets::FIELD
        ) {
            return [];
        }

        $filters = $this->getFilterDefinitions($params);

        if ($allowMultipleFields) {
            $currentFields = GeneralUtility::trimExplode(',', $row[ListFiltersSheets::FIELDS], true);
        } else {
            $currentFields = array_filter([$row[ListFiltersSheets::FIELD]]);
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

        return $this->getModelProperties($cType, $currentFields, $usedFields);
    }

    private function getModelProperties(string $cType, ?array $currentValues = [], ?array $excludedValues = []): array
    {
        $tableName = PluginUtility::getTableName($cType);
        $values = BackendUtility::getAllowedFieldsForTable($tableName);
        $values = array_diff($values, self::DEFAULT_PROPERTIES);
        $values = array_diff($values, $excludedValues);

        $noMatchingLabel = sprintf(
            '[ %s ]',
            LocalizationUtility::translate(
                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'
            )
        );

        $notMatchingValues = array_diff($currentValues, $values);
        $notMatchingValues = array_map(function (string $field) use ($noMatchingLabel) {
            return [sprintf($noMatchingLabel, $field), $field];
        }, $notMatchingValues);

        $properties = array_map(function (string $value) use ($tableName) {
            $label = BackendUtility::getItemLabel($tableName, $value);
            return ['label' => $label, 'value' => $value];
        }, $values);

        return array_merge($notMatchingValues, $properties);
    }

    private function getCurrentFilterValues(array $params): array
    {
        return json_decode($params['row'][$params['field']], true) ?? [];
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
        $settings = $this->getSettings($params, ListFiltersSheets::SHEET_ID);
        $filters = $settings[ListFiltersSheets::FILTERS];
        return array_map(function (array $filter) {
            return $filter[ListFiltersSheets::FILTER];
        }, $filters);
    }

    private function getSettings(array $params, int $sheetId): array
    {
        $flexForm = $this->flexFormService->walkFlexFormNode($params['flexParentDatabaseRow']['pi_flexform']);
        return $flexForm['data'][$sheetId]['lDEF']['settings'];
    }

    private function getPages(array $params): string
    {
        return $params['flexParentDatabaseRow']['pages'];
    }

    private function getRecursive(array $params): int
    {
        return intval($params['flexParentDatabaseRow']['recursive'] ?? 0);
    }

    private function getTableName(array $params): string
    {
        return PluginUtility::getTableName($params['flexParentDatabaseRow']['CType']);
    }

    private function getFieldNames(array $params): array
    {
        $allowMultipleFields = (bool) $params['row'][ListFiltersSheets::ALLOW_MULTIPLE_FIELDS] ?? false;
        $fieldsElement = $allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD;
        $fieldNames = $params['row'][$fieldsElement];
        if (!is_array($fieldNames)) {
            $fieldNames = GeneralUtility::trimExplode(',', $fieldNames, true);
        }
        return array_map(function (string $fieldName) {
            return GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
        }, $fieldNames);
    }
}
