<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class ListFiltersSheets
{
    public const SHEET_ID = 1669190816;
    public const FILTERS = 'filters';
    public const FILTER = 'filter';
    public const FIELD = 'field';
    public const CONJUNCTION = 'conjunction';
    public const APPLIED_VALUES = 'appliedValues';
    public const LABEL = 'label';
    public const EXCLUSIVE = 'exclusive';
    public const AVAILABLE_VALUES = 'availableValues';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(string $extensionName, string $pluginName, string $tableName): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::FILTERS => [
                            'type' => 'array',
                            'section' => 1,
                            'el' => [
                                self::FILTER => [
                                    'type' => 'array',
                                    'title' => self::LOCALLANG . 'filter',
                                    'titleField' => self::FIELD,
                                    'el' => [
                                        self::FIELD => [
                                            'label' => self::LOCALLANG . 'filters.field',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectSingle',
                                                'size' => '1',
                                                'minitems' => '0',
                                                'maxitems' => '1',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getFilterFields',
                                                ItemsProc::PARAMETERS => [
                                                    ItemsProc::PARAMETER_TABLE_NAME => $tableName,
                                                ],
                                            ],
                                        ],
                                        self::CONJUNCTION => [
                                            'label' => self::LOCALLANG . 'filters.conjunction',
                                            'description' => self::LOCALLANG . 'filters.conjunction.description',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectSingle',
                                                'size' => '1',
                                                'minitems' => '1',
                                                'maxitems' => '1',
                                                'multiple' => '0',
                                                'items' => [
                                                    0 => [
                                                        0 => self::LOCALLANG . 'filters.conjunction.items.or',
                                                        1 => 'OR',
                                                    ],
                                                    1 => [
                                                        0 => self::LOCALLANG . 'filters.conjunction.items.and',
                                                        1 => 'AND',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::APPLIED_VALUES => [
                                            'label' => self::LOCALLANG . 'filters.appliedValues',
                                            'description' => self::LOCALLANG . 'filters.appliedValues.description',
                                            'config' => [
                                                'type' => 'user',
                                                'renderType' => 'filterAppliedValues',
                                            ],
                                        ],
                                        self::LABEL => [
                                            'label' => self::LOCALLANG . 'filters.label',
                                            'description' => self::LOCALLANG . 'filters.label.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::EXCLUSIVE => [
                                            'label' => self::LOCALLANG . 'filters.exclusive',
                                            'description' => self::LOCALLANG . 'filters.exclusive.description',
                                            'config' => [
                                                'type' => 'check',
                                                'items' => [
                                                    [''],
                                                ],
                                            ],
                                        ],
                                        self::AVAILABLE_VALUES => [
                                            'label' => self::LOCALLANG . 'filters.availableValues',
                                            'description' => self::LOCALLANG . 'filters.availableValues.description',
                                            'config' => [
                                                'type' => 'user',
                                                'renderType' => 'filterAvailableValues',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
