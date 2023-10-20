<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class DetailSheets
{
    public const SHEET_ID = 1669192667;
    public const PROPERTIES = 'properties';
    public const RECORD = 'record';
    public const SOURCE = 'source';
    public const SOURCE_DEFAULT = '';
    public const SOURCE_RECORD = 'record';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'detail',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::SOURCE => [
                            'label' => self::LOCALLANG . 'detail.source',
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
                                        'label' => self::LOCALLANG . 'detail.source.default',
                                        'value' => self::SOURCE_DEFAULT,
                                    ],
                                    [
                                        'label' => self::LOCALLANG . 'detail.source.record',
                                        'value' => self::SOURCE_RECORD,
                                    ],
                                ],
                                'itemsProcFunc' => ItemsProc::class . '->getDetailSources',
                            ],
                        ],
                        'settings.' . self::RECORD => [
                            'label' => self::LOCALLANG . 'detail.record',
                            'displayCond' => 'FIELD:settings.' . self::SOURCE . ':=:' . self::SOURCE_RECORD,
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'minitems' => '0',
                                'maxitems' => '1',
                                'multiple' => '0',
                                'itemsProcFunc' => ItemsProc::class . '->getDetailRecords',
                            ],
                        ],
                        'settings.' . self::PROPERTIES => [
                            'label' => self::LOCALLANG . 'detail.properties',
                            'description' => self::LOCALLANG . 'detail.properties.description',
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectMultipleSideBySide',
                                'multiple' => '0',
                                'itemsProcFunc' => ItemsProc::class . '->getDetailProperties',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
