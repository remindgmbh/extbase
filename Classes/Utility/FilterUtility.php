<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

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
                    $result[$filterName][] = self::getFieldValues($filterName, $firstLevel);
                } else {
                    foreach ($firstLevel as $secondLevel) {
                        if (is_array($secondLevel)) {
                            $result[$filterName][] = self::getFieldValues($filterName, $secondLevel);
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

    private static function getFieldValues(string $filterName, array $values): array
    {
        $fieldNames = GeneralUtility::trimExplode(',', $filterName);
        $fieldValues = [];
        foreach ($fieldNames as $fieldName) {
            $fieldValues[$fieldName] = $values[$fieldName];
        }
        return $fieldValues;
    }
}
