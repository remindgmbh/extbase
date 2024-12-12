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

    /**
     * @return mixed[]
     */
    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'el' => [
                        'settings.' . self::FILTERS => [
                            'el' => [
                                self::FILTER => [
                                    'disabledField' => self::DISABLED,
                                    'el' => [
                                        self::CONJUNCTION => [
                                            'config' => [
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
                                                'maxitems' => '1',
                                                'minitems' => '1',
                                                'multiple' => '0',
                                                'renderType' => 'selectSingle',
                                                'size' => '1',
                                                'type' => 'select',
                                            ],
                                            'description' => self::LOCALLANG . 'filters.conjunction.description',
                                            'label' => self::LOCALLANG . 'filters.conjunction',
                                        ],
                                        self::DISABLED => [
                                            'config' => [
                                                'items' => [
                                                    [
                                                        'label' => '',
                                                        'value' => 0,
                                                    ],
                                                ],
                                                'type' => 'check',
                                            ],
                                            'label' => self::LOCALLANG . 'filters.disabled',
                                            'onChange' => 'reload',
                                        ],
                                        self::FIELDS => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getPredefinedFilterFields',
                                                'multiple' => '0',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'type' => 'select',
                                            ],
                                            'label' => self::LOCALLANG . 'filters.fields',
                                            'onChange' => 'reload',
                                        ],
                                        self::VALUES => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getPredefinedFilterValues',
                                                'renderType' => 'selectMultipleSideBySideJson',
                                                'type' => 'user',
                                            ],
                                            'label' => self::LOCALLANG . 'filters.values',
                                            'onChange' => 'reload',
                                        ],
                                    ],
                                    'title' => self::LOCALLANG . 'filters.filter',
                                    'titleField' => self::FIELDS,
                                    'type' => 'array',
                                ],
                            ],
                            'section' => 1,
                            'type' => 'array',
                        ],
                    ],
                    'sheetTitle' => self::LOCALLANG . 'filters.predefined',
                    'type' => 'array',
                ],
            ],
        ];
    }
}
