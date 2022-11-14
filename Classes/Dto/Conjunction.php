<?php

namespace Remind\Extbase\Dto;

enum Conjunction: string
{
    case OR = 'OR';
    case AND = 'AND';
}
