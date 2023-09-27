<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FrontendFilter extends Property
{
    private FilterValue $allValues;

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(
        string $name,
        string $label,
        FilterValue $allValues,
        string $prefix,
        string $suffix,
    ) {
        parent::__construct($name, $label, $prefix, $suffix);
        $this->allValues = $allValues;
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
