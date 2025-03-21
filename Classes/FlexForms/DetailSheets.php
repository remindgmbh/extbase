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

    /**
     * @return mixed[]
     */
    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'el' => [
                        'settings.' . self::PROPERTIES => [
                            'config' => [
                                'itemsProcFunc' => ItemsProc::class . '->getDetailProperties',
                                'multiple' => '0',
                                'renderType' => 'selectMultipleSideBySide',
                                'type' => 'select',
                            ],
                            'description' => self::LOCALLANG . 'detail.properties.description',
                            'label' => self::LOCALLANG . 'detail.properties',
                        ],
                        'settings.' . self::RECORD => [
                            'config' => [
                                'itemsProcFunc' => ItemsProc::class . '->getDetailRecords',
                                'maxitems' => '1',
                                'minitems' => '0',
                                'multiple' => '0',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'type' => 'select',
                            ],
                            'displayCond' => 'FIELD:settings.' . self::SOURCE . ':=:' . self::SOURCE_RECORD,
                            'label' => self::LOCALLANG . 'detail.record',
                        ],
                        'settings.' . self::SOURCE => [
                            'config' => [
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
                                'maxitems' => '1',
                                'minitems' => '0',
                                'multiple' => '0',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'type' => 'select',
                            ],
                            'label' => self::LOCALLANG . 'detail.source',
                            'onChange' => 'reload',
                        ],
                    ],
                    'sheetTitle' => self::LOCALLANG . 'detail',
                    'type' => 'array',
                ],
            ],
        ];
    }
}
