<?php

declare(strict_types=1);

namespace Remind\Extbase\FlexForms;

use Remind\Extbase\Backend\ItemsProc;

class ListSheets
{
    public const SHEET_ID = 1669192689;
    public const DETAIL_PAGE = 'detailPage';
    public const ORDER_BY = 'orderBy';
    public const ORDER_DIRECTION = 'orderDirection';
    public const ITEMS_PER_PAGE = 'itemsPerPage';
    public const LIMIT = 'limit';
    private const LOCALLANG = 'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:';

    public static function getSheets(): array
    {
        return [
            self::SHEET_ID => [
                'ROOT' => [
                    'sheetTitle' => self::LOCALLANG . 'list',
                    'type' => 'array',
                    'el' => [
                        'settings.' . self::DETAIL_PAGE => [
                            'label' => self::LOCALLANG . 'list.detailPage',
                            'config' => [
                                'type' => 'group',
                                'allowed' => 'pages',
                                'maxitems' => '1',
                                'minitems' => '0',
                                'size' => '1',
                                'suggestOptions' => [
                                    'default' => [
                                        'additionalSearchFields' => 'nav_title, alias, url',
                                        'addWhere' => 'AND pages.doktype = 1',
                                    ],
                                ],
                            ],
                        ],
                        'settings.' . self::ORDER_BY => [
                            'label' => self::LOCALLANG . 'list.orderBy',
                            'exclude' => '0',
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'minitems' => '0',
                                'maxitems' => '1',
                                'multiple' => '0',
                                'items' => [
                                    [
                                        'label' => self::LOCALLANG . 'list.orderBy.sorting',
                                        'value' => 'sorting',
                                    ],
                                ],
                                'itemsProcFunc' => ItemsProc::class . '->getListOrderByItems',
                            ],
                        ],
                        'settings.' . self::ORDER_DIRECTION => [
                            'label' => self::LOCALLANG . 'list.orderDirection',
                            'exclude' => '0',
                            'config' => [
                                'type' => 'select',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'minitems' => '0',
                                'maxitems' => '1',
                                'multiple' => '0',
                                'items' => [
                                    [
                                        'label' => self::LOCALLANG . 'list.orderDirection.asc',
                                        'value' => 'ASC',
                                    ],
                                    [
                                        'label' => self::LOCALLANG . 'list.orderDirection.desc',
                                        'value' => 'DESC',
                                    ],
                                ],
                            ],
                        ],
                        'settings.' . self::ITEMS_PER_PAGE => [
                            'label' => self::LOCALLANG . 'list.pagination.itemsPerPage',
                            'config' => [
                                'type' => 'input',
                                'size' => '2',
                            ],
                        ],
                        'settings.' . self::LIMIT => [
                            'label' => self::LOCALLANG . 'list.pagination.limit',
                            'config' => [
                                'type' => 'input',
                                'size' => '2',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
