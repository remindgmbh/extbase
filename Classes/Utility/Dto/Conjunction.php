<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility\Dto;

enum Conjunction: string
{
    case OR = 'OR';
    case AND = 'AND';
}
