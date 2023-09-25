<?php

namespace Remind\Extbase\Utility\Dto;

class DatabaseFilter
{
    private string $filterName;

    /**
     * Sequential array (outer array) containing fieldName -> value map (inner array)
     * @var array<array<int|string>> $values
     */
    private array $values;

    private bool $mm = false;

    private Conjunction $conjunction;

    public function __construct(string $filterName, array $values, bool $mm, Conjunction $conjunction)
    {
        $this->filterName = $filterName;
        $this->values = $values;
        $this->mm = $mm;
        $this->conjunction = $conjunction;
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

    public function addValue(array $value): self
    {
        $this->values[] = $value;

        return $this;
    }

    /**
     * @return array<array<string|int>>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): self
    {
        $this->values = $values;

        return $this;
    }

    public function isMm(): bool
    {
        return $this->mm;
    }

    public function setMm(bool $mm): self
    {
        $this->mm = $mm;

        return $this;
    }

    public function getConjunction(): Conjunction
    {
        return $this->conjunction;
    }

    public function setConjunction(Conjunction $conjunction): self
    {
        $this->conjunction = $conjunction;

        return $this;
    }
}
