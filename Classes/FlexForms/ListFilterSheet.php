<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Utility\PluginUtility;

class ListFilterSheet
{
    public const SHEET_ID = 1669190816;
    public const FILTER = 'filter';
    public const CONJUNCTION = 'conjunction';
    public const VALUES = 'values';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheet(string $extensionName): array
    {
        $filter = PluginUtility::getFilters($extensionName);
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filter',
                    'type' => 'array',
                    'el' => array_merge(
                        ...array_map(
                            function (string $fieldName, array $filterConfig) {
                                $label = $filterConfig['label'];
                                $tableName = $filterConfig['table'];

                                return [
                                    implode('.', ['settings', self::FILTER, $fieldName, self::CONJUNCTION])
                                        => self::getConjunction($label),
                                    implode('.', ['settings', self::FILTER, $fieldName, self::VALUES])
                                        => self::getValue($label, $tableName),
                                ];
                            },
                            array_keys($filter),
                            $filter
                        )
                    ),
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
                        0 => self::LOCALLANG . 'filter.mode.or',
                        1 => 'OR',
                    ],
                    1 => [
                        0 => self::LOCALLANG . 'filter.mode.and',
                        1 => 'AND',
                    ],
                ],
            ],
        ];
    }

    private static function getValue(string $label, string $tableName): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'size' => '7',
                'minitems' => '0',
                'multipe' => '0',
                'foreign_table' => $tableName,
            ],
        ];
    }
}
