<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;
use Remind\Extbase\Utility\Dto\Conjunction;

class FrontendFilterSheets
{
    public const SHEET_ID = 1697788482;
    public const CONJUNCTION = 'conjunction';
    public const DISABLED = 'disabled';
    public const DYNAMIC_VALUES = 'dynamicValues';
    public const EXCLUDED_VALUES = 'excludedValues';
    public const EXCLUSIVE = 'exclusive';
    public const FIELDS = 'fields';
    public const FILTER = 'filter';
    public const FILTERS = 'frontendFilters';
    public const LABEL = 'label';
    public const RESET_FILTER_LABEL = 'resetFilterLabel';
    public const RESET_FILTERS_LABEL = 'resetFiltersLabel';
    public const VALUES = 'values';
    public const VALUE_PREFIX = 'valuePrefix';
    public const VALUE_SUFFIX = 'valueSuffix';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'filters.frontend',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::RESET_FILTERS_LABEL => [
                            'label' => self::LOCALLANG . 'filters.resetFiltersLabel',
                            'config' => [
                                'type' => 'input',
                            ],
                        ],
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
                                                'itemsProcFunc' => ItemsProc::class . '->getFrontendFilterFields',
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
                                        self::EXCLUSIVE => [
                                            'label' => self::LOCALLANG . 'filters.exclusive',
                                            'description' => self::LOCALLANG . 'filters.exclusive.description',
                                            'config' => [
                                                'type' => 'check',
                                                'default' => 1,
                                                'items' => [
                                                    [
                                                        'label' => '',
                                                        'value' => 0,
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::RESET_FILTER_LABEL => [
                                            'label' => self::LOCALLANG . 'filters.resetFilterLabel',
                                            'description' => self::LOCALLANG . 'filters.resetFilterLabel.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::DYNAMIC_VALUES => [
                                            'label' => self::LOCALLANG . 'filters.dynamicValues',
                                            'description' => self::LOCALLANG . 'filters.dynamicValues.description',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'check',
                                                'default' => 1,
                                                'items' => [
                                                    [
                                                        'label' => '',
                                                        'value' => 0,
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::EXCLUDED_VALUES => [
                                            'label' => self::LOCALLANG . 'filters.excludedValues',
                                            'description' => self::LOCALLANG . 'filters.excludedValues.description',
                                            'displayCond' => 'FIELD:' . self::DYNAMIC_VALUES . ':REQ:true',
                                            'config' => [
                                                'type' => 'user',
                                                'renderType' => 'selectMultipleSideBySideJson',
                                                'itemsProcFunc' => ItemsProc::class . '->getFrontendFilterValues',
                                            ],
                                        ],
                                        self::VALUES => [
                                            'label' => self::LOCALLANG . 'filters.values',
                                            'displayCond' => 'FIELD:' . self::DYNAMIC_VALUES . ':REQ:false',
                                            'config' => [
                                                'type' => 'user',
                                                'renderType' => 'selectMultipleSideBySideJson',
                                                'itemsProcFunc' => ItemsProc::class . '->getFrontendFilterValues',
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
