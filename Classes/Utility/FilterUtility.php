<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\Utility\Dto\Conjunction;
use Remind\Extbase\Utility\Dto\DatabaseFilter;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class FilterUtility
{
    public static function getAppliedValuesDatabaseFilters(array $settings, string $table): array
    {
        $result = [];

        $filterSettings = $settings[ListFiltersSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[ListFiltersSheets::FILTER] ?? [];

            $disabled = (bool) ($filterSetting[ListFiltersSheets::DISABLED] ?? false);

            $values = array_map(
                function (string $value) {
                    return json_decode($value, true);
                },
                json_decode($filterSetting[ListFiltersSheets::APPLIED_VALUES] ?? '', true) ?? []
            );

            if ($disabled || empty($values)) {
                continue;
            }

            $filterName = self::getFilterName($filterSetting);

            $result[$filterName] = self::getDatabaseFilter($filterSetting, $filterName, $values, $table);
        }
        return $result;
    }

    public static function getDatabaseFilter(
        array $filterSetting,
        string $filterName,
        array $values,
        string $table
    ): DatabaseFilter {
        $conjunction = $filterSetting[ListFiltersSheets::CONJUNCTION] ?? Conjunction::OR->value;
        $conjunction = Conjunction::from(is_array($conjunction) ? $conjunction[0] : $conjunction);
        $fieldTca = BackendUtility::getTcaFieldConfiguration($table, $filterName);
        return new DatabaseFilter(
            $filterName,
            $values,
            isset($fieldTca['MM']),
            $conjunction
        );
    }

    public static function getFilterName(array $filterSetting): string
    {
        $allowMultipleFields = (bool) $filterSetting[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
        $filterName = $filterSetting[$allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD];
        return is_array($filterName) ? $filterName[0] : $filterName;
    }
    public static function normalizeQueryParameters(array $filters): array
    {
        $result = [];
        foreach ($filters as $fieldName => $values) {
            $result[$fieldName] = is_array($values) ? $values : [$values];
        }
        return $result;
    }

    public static function simplifyQueryParameters(array $filters): array
    {
        // if only one argument is defined remove [0] from query parameter
        foreach ($filters as $fieldName => $values) {
            if (count($values) === 1) {
                $filters[$fieldName] = $values[0];
            }
        }
        return $filters;
    }
}
