<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FrontendFilter
{
    private string $filterName = '';

    private string $label = '';
    private string $prefix = '';
    private string $suffix = '';

    private FilterValue $allValues;

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(
        string $filterName,
        string $label,
        FilterValue $allValues,
        string $prefix,
        string $suffix,
    ) {
        $this->label = $label;
        $this->filterName = $filterName;
        $this->allValues = $allValues;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
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

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setSuffix(string $suffix): self
    {
        $this->suffix = $suffix;

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
