<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class DetailDataSheets
{
    public const SHEET_ID = 1669192667;
    public const RECORD = 'record';
    public const SOURCE = 'source';
    public const SOURCE_DEFAULT = '';
    public const SOURCE_RECORD = 'record';
    public const PROPERTIES = 'properties';
    public const PROPERTY = 'property';
    public const FIELD = 'field';
    public const LABEL = 'label';
    public const VALUE_PREFIX = 'valuePrefix';
    public const VALUE_SUFFIX = 'valueSuffix';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'data',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::SOURCE => [
                            'label' => self::LOCALLANG . 'data.source',
                            'onChange' => 'reload',
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'minitems' => '0',
                                'maxitems' => '1',
                                'multiple' => '0',
                                'items' => [
                                   [
                                        'label' => self::LOCALLANG . 'data.source.default',
                                        'value' => self::SOURCE_DEFAULT,
                                    ],
                                    [
                                        'label' => self::LOCALLANG . 'data.source.record',
                                        'value' => self::SOURCE_RECORD,
                                    ],
                                ],
                                'itemsProcFunc' => ItemsProc::class . '->getDetailDataSourceItems',
                            ],
                        ],
                        'settings.' . self::RECORD => [
                            'label' => self::LOCALLANG . 'data.record',
                            'displayCond' => 'FIELD:settings.' . self::SOURCE . ':=:' . self::SOURCE_RECORD,
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'minitems' => '0',
                                'maxitems' => '1',
                                'multiple' => '0',
                                'itemsProcFunc' => ItemsProc::class . '->getDetailDataRecordItems',
                            ],
                        ],
                        'settings.' . self::PROPERTIES => [
                            'type' => 'array',
                            'section' => 1,
                            'el' => [
                                self::PROPERTY => [
                                    'type' => 'array',
                                    'title' => self::LOCALLANG . 'property',
                                    'titleField' => self::FIELD,
                                    'el' => [
                                        self::FIELD => [
                                            'label' => self::LOCALLANG . 'properties.field',
                                            'onChange' => 'reload',
                                            'config' => [
                                                'type' => 'select',
                                                'renderType' => 'selectSingle',
                                                'default' => null,
                                                'size' => '1',
                                                'minitems' => '1',
                                                'maxitems' => '1',
                                                'multiple' => '0',
                                                'itemsProcFunc' => ItemsProc::class . '->getDetailDataPropertyFieldItems',
                                                'items' => [
                                                    [
                                                        'value' => null,
                                                        'label' => self::LOCALLANG . 'properties.field.empty',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        self::LABEL => [
                                            'label' => self::LOCALLANG . 'properties.label',
                                            'description' => self::LOCALLANG . 'properties.label.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::VALUE_PREFIX => [
                                            'label' => self::LOCALLANG . 'properties.valuePrefix',
                                            'description' => self::LOCALLANG . 'properties.valuePrefix.description',
                                            'config' => [
                                                'type' => 'input',
                                            ],
                                        ],
                                        self::VALUE_SUFFIX => [
                                            'label' => self::LOCALLANG . 'properties.valueSuffix',
                                            'description' => self::LOCALLANG . 'properties.valueSuffix.description',
                                            'config' => [
                                                'type' => 'input',
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
