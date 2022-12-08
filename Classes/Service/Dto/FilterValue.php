<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FilterValue
{
    protected int|string $value = '';
    protected string $label = '';
    protected bool $active = false;
    protected bool $disabled = false;
    protected string $link = '';

    public function __construct(int|string $value, string $label)
    {
        $this->value = $value;
        $this->label = $label;
    }

    public function getValue(): int|string
    {
        return $this->value;
    }

    public function setValue(int|string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

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

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }
}
