<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class PropertyOverrideSheets
{
    public const SHEET_ID = 1697808425;
    public const FIELDS = 'fields';
    public const LABEL = 'label';
    public const OVERRIDE = 'override';
    public const OVERRIDES = 'propertyOverrides';
    public const REFERENCE = 'reference';
    public const VALUE_PREFIX = 'valuePrefix';
    public const VALUE_SUFFIX = 'valueSuffix';
    public const VALUE_OVERRIDES = 'valueOverrides';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'propertyOverrides',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::REFERENCE => [
                            'label' => self::LOCALLANG . 'propertyOverrides.reference',
                            'description' => self::LOCALLANG . 'propertyOverrides.reference.description',
                            'onChange' => 'reload',
                            'config' => [
                                'type' => 'group',
                                'maxitems' => 1,
                                'minitems' => 0,
                                'size' => 1,
                                'allowed' => 'tt_content',
                            ],
                        ],
                        'settings.' . self::OVERRIDES => [
                            'type' => 'array',
                            'section' => 1,
                            'displayCond' => 'FIELD:settings.' . self::REFERENCE . ':REQ:false',
                            'el' => [
                                self::OVERRIDE => [
                                    'type' => 'array',
                                    'title' => self::LOCALLANG . 'propertyOverrides.property',
                                    'titleField' => self::FIELDS,
                                    'el' => [
                                        self::FIELDS => [
                                            'label' => self::LOCALLANG . 'propertyOverrides.fields',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getPropertyFields',
                                            ],
                                        ],
                                        self::LABEL => [
                                            'label' => self::LOCALLANG . 'propertyOverrides.label',
                                            'description' => self::LOCALLANG . 'propertyOverrides.label.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::VALUE_PREFIX => [
                                            'label' => self::LOCALLANG . 'propertyOverrides.valuePrefix',
                                            'description' => self::LOCALLANG . 'propertyOverrides.valuePrefix.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::VALUE_SUFFIX => [
                                            'label' => self::LOCALLANG . 'propertyOverrides.valueSuffix',
                                            'description' => self::LOCALLANG . 'propertyOverrides.valueSuffix.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::VALUE_OVERRIDES => [
                                            'label' => self::LOCALLANG . 'propertyOverrides.valueOverrides',
                                            'description' => self::LOCALLANG . 'propertyOverrides.valueOverrides.description',
                                            'config' => [
                                                'type' => 'user',
                                                'renderType' => 'valueLabelPairs',
                                                'itemsProcFunc' => ItemsProc::class . '->getPropertyValues',
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
