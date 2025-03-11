<?php

declare(strict_types=1);

namespace Remind\Extbase\Controller\Dto;

use JsonSerializable;

class FrontendFilter implements JsonSerializable
{
    private string $name = '';

    private string $label = '';

    private FilterValue $resetFilter;

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(
        string $name,
        string $label,
        FilterValue $resetFilter
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->resetFilter = $resetFilter;
    }

    /**
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'name' => $this->name,
            'resetFilter' => $this->resetFilter,
            'values' => $this->values,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getResetFilter(): FilterValue
    {
        return $this->resetFilter;
    }

    public function setResetFilter(FilterValue $resetFilter): self
    {
        $this->resetFilter = $resetFilter;

        return $this;
    }

    /**
     * @return FilterValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function addValue(FilterValue $value): self
    {
        $this->values[] = $value;

        return $this;
    }
}
