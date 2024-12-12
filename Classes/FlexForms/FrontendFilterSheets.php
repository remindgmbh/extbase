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
                                        self::DYNAMIC_VALUES => [
                                            'config' => [
                                                'default' => 1,
                                                'items' => [
                                                    [
                                                        'label' => '',
                                                        'value' => 0,
                                                    ],
                                                ],
                                                'type' => 'check',
                                            ],
                                            'description' => self::LOCALLANG . 'filters.dynamicValues.description',
                                            'label' => self::LOCALLANG . 'filters.dynamicValues',
                                            'onChange' => 'reload',
                                        ],
                                        self::EXCLUDED_VALUES => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getFrontendFilterValues',
                                                'renderType' => 'selectMultipleSideBySideJson',
                                                'type' => 'user',
                                            ],
                                            'description' => self::LOCALLANG . 'filters.excludedValues.description',
                                            'displayCond' => 'FIELD:' . self::DYNAMIC_VALUES . ':REQ:true',
                                            'label' => self::LOCALLANG . 'filters.excludedValues',
                                        ],
                                        self::EXCLUSIVE => [
                                            'config' => [
                                                'default' => 1,
                                                'items' => [
                                                    [
                                                        'label' => '',
                                                        'value' => 0,
                                                    ],
                                                ],
                                                'type' => 'check',
                                            ],
                                            'description' => self::LOCALLANG . 'filters.exclusive.description',
                                            'label' => self::LOCALLANG . 'filters.exclusive',
                                        ],
                                        self::FIELDS => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getFrontendFilterFields',
                                                'multiple' => '0',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'type' => 'select',
                                            ],
                                            'label' => self::LOCALLANG . 'filters.fields',
                                            'onChange' => 'reload',
                                        ],
                                        self::RESET_FILTER_LABEL => [
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                            'description' => self::LOCALLANG . 'filters.resetFilterLabel.description',
                                            'label' => self::LOCALLANG . 'filters.resetFilterLabel',
                                        ],
                                        self::VALUES => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getFrontendFilterValues',
                                                'renderType' => 'selectMultipleSideBySideJson',
                                                'type' => 'user',
                                            ],
                                            'displayCond' => 'FIELD:' . self::DYNAMIC_VALUES . ':REQ:false',
                                            'label' => self::LOCALLANG . 'filters.values',
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
                        'settings.' . self::RESET_FILTERS_LABEL => [
                            'config' => [
                                'type' => 'input',
                            ],
                            'label' => self::LOCALLANG . 'filters.resetFiltersLabel',
                        ],
                    ],
                    'sheetTitle' => self::LOCALLANG . 'filters.frontend',
                    'type' => 'array',
                ],
            ],
        ];
    }
}
