<?php

declare(strict_types=1);

namespace Remind\Extbase\Controller\Dto;

use JsonSerializable;

class FilterValue implements JsonSerializable
{
    /**
     * @var mixed[]
     */
    protected array $value = [];

    protected string $label = '';

    protected bool $active = false;

    protected int $count = 0;

    protected string $link = '';

    /**
     * @param mixed[] $value
     */
    public function __construct(array $value, string $label)
    {
        $this->value = $value;
        $this->label = $label;
    }

    /**
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        return [
            'active' => $this->active,
            'count' => $this->count,
            'label' => $this->label,
            'link' => $this->link,
            'value' => $this->value,
        ];
    }

    /**
     * @return mixed[]
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @param mixed[] $value
     */
    public function setValue(array $value): self
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

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

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
