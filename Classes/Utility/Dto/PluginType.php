<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility\Dto;

enum PluginType: string
{
    case DETAIL = 'detail';
    case FILTERABLE_LIST = 'filterableList';
    case SELECTION_LIST = 'selectionList';
}
