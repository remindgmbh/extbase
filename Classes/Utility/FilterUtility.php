<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\FlexForms\PredefinedFilterSheets;
use Remind\Extbase\Utility\Dto\Conjunction;
use Remind\Extbase\Utility\Dto\DatabaseFilter;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class FilterUtility
{
    /**
     * @param mixed[] $settings
     * @return DatabaseFilter[]
     */
    public static function getPredefinedDatabaseFilters(array $settings, string $table): array
    {
        $result = [];

        $filterSettings = $settings[PredefinedFilterSheets::FILTERS] ?? [];
        foreach ($filterSettings as $filterSetting) {
            $filterSetting = $filterSetting[PredefinedFilterSheets::FILTER] ?? [];

            $disabled = (bool) ($filterSetting[PredefinedFilterSheets::DISABLED] ?? false);

            $values = array_map(
                function (string $value) {
                    return json_decode($value, true);
                },
                json_decode($filterSetting[PredefinedFilterSheets::VALUES] ?? '', true) ?? []
            );

            $filterName = self::getFilterName($filterSetting);

            if (
                !$filterName ||
                $disabled ||
                empty($values)
            ) {
                continue;
            }

            $result[$filterName] = self::getDatabaseFilter($filterSetting, $filterName, $values, $table);
        }
        return $result;
    }

    /**
     * @param mixed[] $filterSetting
     * @param mixed[] $values
     */
    public static function getDatabaseFilter(
        array $filterSetting,
        string $filterName,
        array $values,
        string $table
    ): DatabaseFilter {
        $conjunction = $filterSetting[PredefinedFilterSheets::CONJUNCTION] ?? Conjunction::OR->value;
        $conjunction = Conjunction::from(is_array($conjunction) ? $conjunction[0] : $conjunction);
        $fieldTca = BackendUtility::getTcaFieldConfiguration($table, $filterName);
        return new DatabaseFilter(
            $filterName,
            $values,
            isset($fieldTca['MM']),
            $conjunction
        );
    }

    /**
     * @param mixed[] $filterSetting
     */
    public static function getFilterName(array $filterSetting): ?string
    {
        $filterName = $filterSetting[PredefinedFilterSheets::FIELDS] ?? null;
        return is_array($filterName) ? (empty($filterName) ? null : $filterName[0]) : $filterName;
    }

    /**
     * @param mixed[] $filters
     * @return mixed[]
     */
    public static function normalizeQueryParameters(array $filters): array
    {
        $result = [];
        foreach ($filters as $fieldName => $values) {
            $result[$fieldName] = is_array($values) ? $values : [$values];
        }
        return $result;
    }

    /**
     * @param mixed[] $filters
     * @return mixed[]
     */
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
