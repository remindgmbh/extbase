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
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(string $extensionName): array
    {
        $filters = PluginUtility::getFilters($extensionName);
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
                                => self::getValue($label, $tableName),
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
                            implode('.', ['settings', self::FRONTEND_FILTERS, $fieldName, self::VALUES])
                                => self::getValue($label, $tableName),
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
                function (string $fieldName, array $filterConfig) use ($callback) {
                    $label = $filterConfig['label'];
                    $tableName = $filterConfig['table'] ?? null;
                    return $callback($fieldName, $label, $tableName);
                },
                array_keys($filters),
                $filters
            )
        );
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

    private static function getValue(string $label, ?string $tableName): array
    {
        return [
            'label' => $label,
            'config' => $tableName ? [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'size' => '7',
                'minitems' => '0',
                'multipe' => '0',
                'foreign_table' => $tableName,
            ] : [
                'type' => 'text',
                'renderType' => 'list',
            ],
        ];
    }
}
