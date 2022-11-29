<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class FilterValue
{
    protected AbstractEntity|string|null $value = null;
    protected bool $active = false;
    protected bool $disabled = false;

    public function __construct(AbstractEntity|string $value)
    {
        $this->value = $value;
    }

    public function getArgumentValue(): string
    {
        return $this->value instanceof AbstractEntity ? strval($this->value->getUid()) : $this->value;
    }

    public function getValue(): AbstractEntity|string|null
    {
        return $this->value;
    }

    public function setValue(AbstractEntity|string|null $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;

        return $this;
    }
}
