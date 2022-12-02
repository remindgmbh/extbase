<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Utility\PluginUtility;

class ListFiltersSheets
{
    public const BACKEND_SHEET_ID = 1669190816;
    public const FRONTEND_SHEET_ID = 1669191779;
    public const BACKEND_FILTERS = 'backendFilters';
    public const FRONTEND_FILTERS = 'frontendFilters';
    public const CONJUNCTION = 'conjunction';
    public const VALUES = 'values';
    public const EXCLUSIVE = 'exclusive';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(string $extensionName, string $pluginName): array
    {
        $filters = PluginUtility::getFilters($extensionName, $pluginName);
        return [
            self::BACKEND_SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters.backend',
                    'type' => 'array',
                    'el' => self::getFilters($filters, function ($fieldName, $label, $tableName) {
                        return [
                            implode('.', ['settings', self::BACKEND_FILTERS, $fieldName, self::CONJUNCTION])
                                => self::getConjunction($label),
                            implode('.', ['settings', self::BACKEND_FILTERS, $fieldName, self::VALUES])
                                => self::getBackendValue($label, $tableName, $fieldName),
                        ];
                    }),
                ],
            ],
            self::FRONTEND_SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters.frontend',
                    'type' => 'array',
                    'el' => self::getFilters($filters, function ($fieldName, $label, $tableName) {
                        return [
                            implode('.', ['settings', self::FRONTEND_FILTERS, $fieldName, self::EXCLUSIVE])
                                => self::getExclusive($label),
                            implode('.', ['settings', self::FRONTEND_FILTERS, $fieldName, self::VALUES])
                                => self::getFrontendValue($label, $tableName, $fieldName),
                        ];
                    }),
                ],
            ],
        ];
    }

    private static function getFilters(array $filters, callable $callback): array
    {
        return array_merge(
            ...array_map(
                function (array $filterConfig) use ($callback) {
                    $fieldName = $filterConfig[PluginUtility::FILTER_FIELD_NAME];
                    $label = $filterConfig[PluginUtility::FILTER_LABEL];
                    $tableName = $filterConfig[PluginUtility::FILTER_TABLE_NAME];
                    return $callback($fieldName, $label, $tableName);
                },
                $filters
            )
        );
    }

    private static function getExclusive(string $label): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'check',
                'items' => [
                    [''],
                ],
            ],
        ];
    }

    private static function getConjunction(string $label): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'size' => '1',
                'minitems' => '1',
                'maxitems' => '1',
                'multiple' => '0',
                'items' => [
                    0 => [
                        0 => self::LOCALLANG . 'filters.backend.conjunction.or',
                        1 => 'OR',
                    ],
                    1 => [
                        0 => self::LOCALLANG . 'filters.backend.conjunction.and',
                        1 => 'AND',
                    ],
                ],
            ],
        ];
    }

    private static function getBackendValue(string $label, string $tableName, string $fieldName): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'user',
                'renderType' => 'selectMultipleSideBySideJson',
                'tableName' => $tableName,
                'fieldName' => $fieldName,
            ],
        ];
    }

    private static function getFrontendValue(string $label, string $tableName, string $fieldName): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'user',
                'renderType' => 'frontendFilter',
                'tableName' => $tableName,
                'fieldName' => $fieldName,
            ],
        ];
    }
}
