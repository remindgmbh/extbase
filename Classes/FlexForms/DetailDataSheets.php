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
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(string $extensionName, string $tableName): array
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
                                    0 => [
                                        0 => self::LOCALLANG . 'data.source.default',
                                        1 => self::SOURCE_DEFAULT,
                                    ],
                                    1 => [
                                        0 => self::LOCALLANG . 'data.source.record',
                                        1 => self::SOURCE_RECORD,
                                    ],
                                ],
                                'itemsProcFunc' => ItemsProc::class . '->getDetailSources',
                                ItemsProc::PARAMETERS => [
                                    ItemsProc::PARAMETER_EXTENSION_NAME => $extensionName,
                                ],
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
                                'itemsProcFunc' => ItemsProc::class . '->getRecordsInPages',
                                ItemsProc::PARAMETERS => [
                                    ItemsProc::PARAMETER_TABLE_NAME => $tableName,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
