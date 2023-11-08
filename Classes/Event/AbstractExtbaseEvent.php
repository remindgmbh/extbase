<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

abstract class AbstractExtbaseEvent
{
    protected string $extensionName;

    public function __construct(string $extensionName)
    {
        $this->extensionName = $extensionName;
    }

    public function getExtensionName(): string
    {
        return $this->extensionName;
    }
}
