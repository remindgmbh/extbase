<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FrontendFilter
{
    private string $name = '';
    private string $label = '';
    private FilterValue $allValues;

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(
        string $name,
        string $label,
        FilterValue $allValues
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->allValues = $allValues;
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

    public function getAllValues(): FilterValue
    {
        return $this->allValues;
    }

    public function setAllValues(FilterValue $allValues): self
    {
        $this->allValues = $allValues;

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
