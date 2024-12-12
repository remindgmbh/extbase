<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class SelectionSheets
{
    public const SHEET_ID = 1669192705;
    public const RECORDS = 'records';
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
                        'settings.' . self::RECORDS => [
                            'config' => [
                                'itemsProcFunc' => ItemsProc::class . '->getSelectionRecords',
                                'minitems' => '0',
                                'multiple' => '0',
                                'renderType' => 'selectMultipleSideBySide',
                                'size' => '7',
                                'type' => 'select',
                            ],
                            'label' => self::LOCALLANG . 'selection.records',
                        ],
                    ],
                    'sheetTitle' => self::LOCALLANG . 'selection',
                    'type' => 'array',
                ],
            ],
        ];
    }
}
