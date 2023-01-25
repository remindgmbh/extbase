<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

class FilterUtility
{
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
