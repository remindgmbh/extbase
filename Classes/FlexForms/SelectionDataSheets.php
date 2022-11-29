<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class SelectionDataSheets
{
    public const SHEET_ID = 1669192705;
    public const RECORDS = 'records';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(string $tableName): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'data',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::RECORDS => [
                            'label' => self::LOCALLANG . 'data.records',
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectMultipleSideBySide',
                                'size' => '7',
                                'minitems' => '0',
                                'multiple' => '0',
                                'itemsProcFunc' => ItemsProc::class . '->getRecordsInPages',
                                'itemsProcFuncParameters' => [
                                    'tableName' => $tableName,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
