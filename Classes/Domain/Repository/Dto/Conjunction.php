<?php

namespace Remind\Extbase\Domain\Repository\Dto;

enum Conjunction: string
{
    case OR = 'OR';
    case AND = 'AND';
}
