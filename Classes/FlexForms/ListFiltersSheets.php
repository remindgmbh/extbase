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
    public const LABEL = 'label';
    public const TABLE_NAME = 'tableName';
    public const FIELD_NAME = 'fieldName';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(string $extensionName, string $pluginName): array
    {
        $filters = PluginUtility::getFilters($extensionName, $pluginName);
        return [
            self::BACKEND_SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters.backend',
                    'type' => 'array',
                    'el' => self::getFilters($filters, function ($fieldName, $tableName) {
                        return [
                            implode('.', ['settings', self::BACKEND_FILTERS, $fieldName, self::CONJUNCTION])
                             => self::getConjunction(),
                            implode('.', ['settings', self::BACKEND_FILTERS, $fieldName, self::VALUES])
                             => self::getBackendValue($tableName, $fieldName),
                        ];
                    }),
                ],
            ],
            self::FRONTEND_SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters.frontend',
                    'type' => 'array',
                    'el' => self::getFilters($filters, function ($fieldName, $tableName) {
                        return [
                            implode('.', ['settings', self::FRONTEND_FILTERS, $fieldName, self::LABEL])
                             => self::getLabel(),
                            implode('.', ['settings', self::FRONTEND_FILTERS, $fieldName, self::EXCLUSIVE])
                             => self::getExclusive(),
                            implode('.', ['settings', self::FRONTEND_FILTERS, $fieldName, self::VALUES])
                             => self::getFrontendValue($tableName, $fieldName),
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
                    $tableName = $filterConfig[PluginUtility::FILTER_TABLE_NAME];
                    return $callback($fieldName, $tableName);
                },
                $filters
            )
        );
    }

    private static function getLabel(): array
    {
        return [
            'config' => [
                'type' => 'input',
            ],
        ];
    }

    private static function getExclusive(): array
    {
        return [
            'config' => [
                'type' => 'check',
                'items' => [
                    [''],
                ],
            ],
        ];
    }

    private static function getConjunction(): array
    {
        return [
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

    private static function getBackendValue(string $tableName, string $fieldName): array
    {
        return [
            'config' => [
                'type' => 'user',
                'renderType' => 'selectMultipleSideBySideJson',
                self::TABLE_NAME => $tableName,
                self::FIELD_NAME => $fieldName,
            ],
        ];
    }

    private static function getFrontendValue(string $tableName, string $fieldName): array
    {
        return [
            'config' => [
                'type' => 'user',
                'renderType' => 'frontendFilter',
                self::TABLE_NAME => $tableName,
                self::FIELD_NAME => $fieldName,
            ],
        ];
    }
}
