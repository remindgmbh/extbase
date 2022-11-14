<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Utility\PluginUtility;

class FilterSheet
{
    public const SHEET_ID = 1669191779;
    public const FILTER = 'filter';
    public const VALUES = 'values';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheet(string $extensionName): array
    {
        $filters = PluginUtility::getFilters($extensionName);
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
                                    implode('.', ['settings', self::FILTER, $fieldName, self::VALUES])
                                        => self::getValue($label, $tableName),
                                ];
                            },
                            array_keys($filters),
                            $filters
                        )
                    ),
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
