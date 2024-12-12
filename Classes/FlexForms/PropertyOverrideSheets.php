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

    /**
     * @return mixed[]
     */
    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'el' => [
                        'settings.' . self::OVERRIDES => [
                            'displayCond' => 'FIELD:settings.' . self::REFERENCE . ':REQ:false',
                            'el' => [
                                self::OVERRIDE => [
                                    'el' => [
                                        self::FIELDS => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getPropertyFields',
                                                'multiple' => '0',
                                                'renderType' => 'selectMultipleSideBySide',
                                                'type' => 'select',
                                            ],
                                            'label' => self::LOCALLANG . 'propertyOverrides.fields',
                                            'onChange' => 'reload',
                                        ],
                                        self::LABEL => [
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                            'description' => self::LOCALLANG . 'propertyOverrides.label.description',
                                            'label' => self::LOCALLANG . 'propertyOverrides.label',
                                        ],
                                        self::VALUE_OVERRIDES => [
                                            'config' => [
                                                'itemsProcFunc' => ItemsProc::class . '->getPropertyValues',
                                                'renderType' => 'valueLabelPairs',
                                                'type' => 'user',
                                            ],
                                            'description' => self::LOCALLANG . 'propertyOverrides.valueOverrides.description',
                                            'label' => self::LOCALLANG . 'propertyOverrides.valueOverrides',
                                        ],
                                        self::VALUE_PREFIX => [
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                            'description' => self::LOCALLANG . 'propertyOverrides.valuePrefix.description',
                                            'label' => self::LOCALLANG . 'propertyOverrides.valuePrefix',
                                        ],
                                        self::VALUE_SUFFIX => [
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                            'description' => self::LOCALLANG . 'propertyOverrides.valueSuffix.description',
                                            'label' => self::LOCALLANG . 'propertyOverrides.valueSuffix',
                                        ],
                                    ],
                                    'title' => self::LOCALLANG . 'propertyOverrides.property',
                                    'titleField' => self::FIELDS,
                                    'type' => 'array',
                                ],
                            ],
                            'section' => 1,
                            'type' => 'array',
                        ],
                        'settings.' . self::REFERENCE => [
                            'config' => [
                                'allowed' => 'tt_content',
                                'maxitems' => 1,
                                'minitems' => 0,
                                'size' => 1,
                                'type' => 'group',
                            ],
                            'description' => self::LOCALLANG . 'propertyOverrides.reference.description',
                            'label' => self::LOCALLANG . 'propertyOverrides.reference',
                            'onChange' => 'reload',
                        ],
                    ],
                    'sheetTitle' => self::LOCALLANG . 'propertyOverrides',
                    'type' => 'array',
                ],
            ],
        ];
    }
}
