<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;
use Remind\Extbase\Domain\Repository\Dto\Conjunction;

class ListFiltersSheets
{
    public const ALLOW_MULTIPLE_FIELDS = 'allowMultipleFields';
    public const APPLIED_VALUES = 'appliedValues';
    public const AVAILABLE_VALUES = 'availableValues';
    public const CONJUNCTION = 'conjunction';
    public const DISABLED = 'disabled';
    public const EXCLUSIVE = 'exclusive';
    public const FIELD = 'field';
    public const FIELDS = 'fields';
    public const FILTER = 'filter';
    public const FILTERS = 'filters';
    public const LABEL = 'label';
    public const SHEET_ID = 1669190816;
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
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
                                    'titleField' => self::FIELDS,
                                    'titleField_alt' => self::FIELD,
                                    'disabledField' => self::DISABLED,
                                    'el' => [
                                        self::DISABLED => [
                                            'label' => self::LOCALLANG . 'filters.disabled',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'check',
                                                'items' => [
                                                    [''],
                                                ],
                                            ],
                                        ],
                                        self::ALLOW_MULTIPLE_FIELDS => [
                                            'label' => self::LOCALLANG . 'filters.multipleFields',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'check',
                                                'items' => [
                                                    [''],
                                                ],
                                            ],
                                        ],
                                        self::FIELD => [
                                            'label' => self::LOCALLANG . 'filters.field',
                                            'onChange' => 'reload',
                                            'displayCond' => 'FIELD:' . self::ALLOW_MULTIPLE_FIELDS . ':REQ:false',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectSingle',
                                                'size' => '1',
                                                'minitems' => '1',
                                                'maxitems' => '1',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getFilterFields',
                                            ],
                                        ],
                                        self::FIELDS => [
                                            'label' => self::LOCALLANG . 'filters.fields',
                                            'onChange' => 'reload',
                                            'displayCond' => 'FIELD:' . self::ALLOW_MULTIPLE_FIELDS . ':REQ:true',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'minitems' => '2',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getFilterFields',
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
                                                        1 => Conjunction::OR->value,
                                                    ],
                                                    1 => [
                                                        0 => self::LOCALLANG . 'filters.conjunction.items.and',
                                                        1 => Conjunction::AND->value,
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::APPLIED_VALUES => [
                                            'label' => self::LOCALLANG . 'filters.appliedValues',
                                            'description' => self::LOCALLANG . 'filters.appliedValues.description',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'minitems' => '0',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getAppliedFilterValues',
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
                                                'renderType' => 'valueLabelPairs',
                                                'itemsProcFunc' => ItemsProc::class . '->getAvailableFilterValues',
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
