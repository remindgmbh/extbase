<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FrontendFilter
{
    private string $fieldName = '';

    private string $label = '';

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(string $fieldName, string $label)
    {
        $this->label = $label;
        $this->fieldName = $fieldName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;

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
