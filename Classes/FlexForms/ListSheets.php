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
    public const PROPERTIES = 'properties';
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
                        'settings.' . self::DETAIL_PAGE => [
                            'config' => [
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
                                'type' => 'group',
                            ],
                            'label' => self::LOCALLANG . 'list.detailPage',
                        ],
                        'settings.' . self::ITEMS_PER_PAGE => [
                            'config' => [
                                'size' => '2',
                                'type' => 'input',
                            ],
                            'label' => self::LOCALLANG . 'list.pagination.itemsPerPage',
                        ],
                        'settings.' . self::LIMIT => [
                            'config' => [
                                'size' => '2',
                                'type' => 'input',
                            ],
                            'label' => self::LOCALLANG . 'list.pagination.limit',
                        ],
                        'settings.' . self::ORDER_BY => [
                            'config' => [
                                'items' => [
                                    [
                                        'label' => self::LOCALLANG . 'list.orderBy.sorting',
                                        'value' => 'sorting',
                                    ],
                                ],
                                'itemsProcFunc' => ItemsProc::class . '->getListOrderByItems',
                                'maxitems' => '1',
                                'minitems' => '0',
                                'multiple' => '0',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'type' => 'select',
                            ],
                            'exclude' => '0',
                            'label' => self::LOCALLANG . 'list.orderBy',
                        ],
                        'settings.' . self::ORDER_DIRECTION => [
                            'config' => [
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
                                'maxitems' => '1',
                                'minitems' => '0',
                                'multiple' => '0',
                                'renderType' => 'selectSingle',
                                'size' => '1',
                                'type' => 'select',
                            ],
                            'exclude' => '0',
                            'label' => self::LOCALLANG . 'list.orderDirection',
                        ],
                        'settings.' . self::PROPERTIES => [
                            'config' => [
                                'itemsProcFunc' => ItemsProc::class . '->getListProperties',
                                'multiple' => '0',
                                'renderType' => 'selectMultipleSideBySide',
                                'type' => 'select',
                            ],
                            'description' => self::LOCALLANG . 'list.properties.description',
                            'label' => self::LOCALLANG . 'list.properties',
                        ],
                    ],
                    'sheetTitle' => self::LOCALLANG . 'list',
                    'type' => 'array',
                ],
            ],
        ];
    }
}
