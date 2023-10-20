<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;
use Remind\Extbase\Utility\Dto\Conjunction;

class PredefinedFilterSheets
{
    public const SHEET_ID = 1669190816;
    public const CONJUNCTION = 'conjunction';
    public const DISABLED = 'disabled';
    public const FIELDS = 'fields';
    public const FILTER = 'filter';
    public const FILTERS = 'predefinedFilters';
    public const VALUES = 'values';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters.predefined',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::FILTERS => [
                            'type' => 'array',
                            'section' => 1,
                            'el' => [
                                self::FILTER => [
                                    'type' => 'array',
                                    'title' => self::LOCALLANG . 'filters.filter',
                                    'titleField' => self::FIELDS,
                                    'disabledField' => self::DISABLED,
                                    'el' => [
                                        self::DISABLED => [
                                            'label' => self::LOCALLANG . 'filters.disabled',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'check',
                                                'items' => [
                                                    [
                                                        'label' => '',
                                                        'value' => 0,
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::FIELDS => [
                                            'label' => self::LOCALLANG . 'filters.fields',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getPredefinedFilterFields',
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
                                                    [
                                                        'label' => self::LOCALLANG . 'filters.conjunction.items.or',
                                                        'value' => Conjunction::OR->value,
                                                    ],
                                                    [
                                                        'label' => self::LOCALLANG . 'filters.conjunction.items.and',
                                                        'value' => Conjunction::AND->value,
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::VALUES => [
                                            'label' => self::LOCALLANG . 'filters.values',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'user',
                                                'renderType' => 'selectMultipleSideBySideJson',
                                                'itemsProcFunc' => ItemsProc::class . '->getPredefinedFilterValues',
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
