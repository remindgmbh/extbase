<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FrontendFilter
{
    private string $filterName = '';

    private string $label = '';

    private string $allValuesLink = '';

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(string $filterName, string $label, string $allValuesLink)
    {
        $this->label = $label;
        $this->filterName = $filterName;
        $this->allValuesLink = $allValuesLink;
    }

    public function getFilterName(): string
    {
        return $this->filterName;
    }

    public function setFilterName(string $filterName): self
    {
        $this->filterName = $filterName;

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

    public function getAllValuesLink(): string
    {
        return $this->allValuesLink;
    }

    public function setAllValuesLink(string $allValuesLink): self
    {
        $this->allValuesLink = $allValuesLink;

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
