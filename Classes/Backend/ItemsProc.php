<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend;

use Remind\Extbase\FlexForms\DetailSheets;
use Remind\Extbase\FlexForms\FrontendFilterSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\PredefinedFilterSheets;
use Remind\Extbase\FlexForms\PropertyOverrideSheets;
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

    public function getListProperties(array $params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $cType = $flexParentDatabaseRow['CType'];

        $currentValues = GeneralUtility::trimExplode(',', $params['row']['settings.' . ListSheets::PROPERTIES], true);

        $properties = $this->getModelProperties($cType, $currentValues);

        array_push($params['items'], ...$properties);
    }

    public function getDetailSources(array &$params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $sources = PluginUtility::getDetailSources($flexParentDatabaseRow['CType']);
        foreach ($sources as $source) {
            $params['items'][] = ['label' => $source['label'], 'value' => $source['value']];
        }
    }

    public function getDetailRecords(array &$params): void
    {
        array_push($params['items'], ...$this->getRecordsInPages($params));
    }

    public function getDetailProperties(array $params): void
    {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $cType = $flexParentDatabaseRow['CType'];

        $currentValues = GeneralUtility::trimExplode(',', $params['row']['settings.' . DetailSheets::PROPERTIES], true);

        $properties = $this->getModelProperties($cType, $currentValues);

        array_push($params['items'], ...$properties);
    }

    public function getSelectionRecords(array &$params): void
    {
        array_push($params['items'], ...$this->getRecordsInPages($params));
    }

    public function getPredefinedFilterFields(array $params): void
    {
        array_push(
            $params['items'],
            ...$this->getModelPropertiesForSection(
                $params,
                PredefinedFilterSheets::SHEET_ID,
                PredefinedFilterSheets::FILTERS,
                PredefinedFilterSheets::FILTER,
                PredefinedFilterSheets::FIELDS,
            )
        );
    }

    public function getPredefinedFilterValues(array &$params): void
    {
        $currentValues = json_decode($params['row'][$params['field']], true) ?? [];
        $items = $this->databaseService->getAvailableFieldValues(
            $this->getSysLanguageUid($params),
            $this->getTableName($params),
            $this->getFieldNames($params, PredefinedFilterSheets::FIELDS),
            $this->getPages($params),
            $this->getRecursive($params),
        );
        $this->addInvalidValues($items, $currentValues);
        array_push($params['items'], ...$items);
    }

    public function getFrontendFilterFields(array &$params): void
    {
        array_push(
            $params['items'],
            ...$this->getModelPropertiesForSection(
                $params,
                FrontendFilterSheets::SHEET_ID,
                FrontendFilterSheets::FILTERS,
                FrontendFilterSheets::FILTER,
                FrontendFilterSheets::FIELDS,
            )
        );
    }

    public function getFrontendFilterValues(array &$params): void
    {
        $tableName = $this->getTableName($params);

        $filters = FilterUtility::getPredefinedDatabaseFilters(
            $this->getSettings($params, PredefinedFilterSheets::SHEET_ID),
            $tableName,
        );

        $items = $this->databaseService->getAvailableFieldValues(
            $this->getSysLanguageUid($params),
            $tableName,
            $this->getFieldNames($params, FrontendFilterSheets::FIELDS),
            $this->getPages($params),
            $this->getRecursive($params),
            $filters,
        );

        $currentValues = json_decode($params['row'][$params['field']], true) ?? [];

        $this->addInvalidValues($items, $currentValues);

        array_push($params['items'], ...$items);
    }

    public function getPropertyFields(array &$params): void
    {
        array_push(
            $params['items'],
            ...$this->getModelPropertiesForSection(
                $params,
                PropertyOverrideSheets::SHEET_ID,
                PropertyOverrideSheets::OVERRIDES,
                PropertyOverrideSheets::OVERRIDE,
                PropertyOverrideSheets::FIELDS,
            )
        );
    }

    public function getPropertyValues(array &$params): void
    {
        $tableName = $this->getTableName($params);

        if ($tableName) {
            $items = $this->databaseService->getAvailableFieldValues(
                $this->getSysLanguageUid($params),
                $tableName,
                $this->getFieldNames($params, PropertyOverrideSheets::FIELDS),
            );

            array_push($params['items'], ...$items);
        }
    }

    private function getRecordsInPages(array &$params): array
    {
        return $this->databaseService->getRecords(
            $this->getSysLanguageUid($params),
            $this->getTableName($params),
            $this->getPages($params),
            $this->getRecursive($params),
        );
    }

    private function getModelPropertiesForSection(
        array &$params,
        int $sheetId,
        string $sectionName,
        string $elementName,
        string $valueElementName
    ): array {
        $flexParentDatabaseRow = $params['flexParentDatabaseRow'];
        $cType = $flexParentDatabaseRow['CType'];
        $row = $params['row'];

        $settings = $this->getSettings($params, $sheetId);
        $sections = $settings[$sectionName];
        $sections = array_map(function (array $section) use ($elementName) {
            return $section[$elementName];
        }, $sections);

        $currentValues = GeneralUtility::trimExplode(',', $row[$valueElementName], true);

        $usedFields = array_reduce($sections, function (array $result, array $section) use ($currentValues, $valueElementName) {
            $fields = $section[$valueElementName] ?? [];
            $fields = is_array($fields) ? $fields : GeneralUtility::trimExplode(',', $fields, true);
            foreach ($fields as $field) {
                if (!in_array($field, $currentValues)) {
                    $result[] = $field;
                }
            }
            return $result;
        }, []);

        return $this->getModelProperties($cType, $currentValues, $usedFields);
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

    private function getSysLanguageUid(array $params): int
    {
        return (int) $params['flexParentDatabaseRow']['sys_language_uid'];
    }

    private function getTableName(array $params): string
    {
        return PluginUtility::getTableName($params['flexParentDatabaseRow']['CType']);
    }

    private function getFieldNames(array $params, string $fieldsElementName): array
    {
        $fieldNames = $params['row'][$fieldsElementName];
        if (!is_array($fieldNames)) {
            $fieldNames = GeneralUtility::trimExplode(',', $fieldNames, true);
        }
        return array_map(function (string $fieldName) {
            return GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
        }, $fieldNames);
    }
}
