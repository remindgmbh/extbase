<?php

declare(strict_types=1);

namespace Remind\Extbase\Event\Enum;

enum SerializeEntityEventType
{
    case List;
    case Detail;
}
