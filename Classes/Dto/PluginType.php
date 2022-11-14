<?php

declare(strict_types=1);

namespace Remind\Extbase\Dto;

enum PluginType: string
{
    case DETAIL = 'detail';
    case FILTER = 'filter';
    case FILTERABLE_LIST = 'filterableList';
    case SELECTION_LIST = 'selectionList';
}
