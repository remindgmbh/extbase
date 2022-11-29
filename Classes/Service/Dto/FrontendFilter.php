<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FrontendFilter
{
    private string $fieldName = '';

    private string $label = '';

    private bool $mm = false;

    /**
     * @var FilterValue[] $values
     */
    private array $values = [];

    public function __construct(string $fieldName, string $label, bool $mm)
    {
        $this->label = $label;
        $this->fieldName = $fieldName;
        $this->mm = $mm;
    }

    /**
     * @return FilterValue[]
     */
    public function getActiveValues(): array
    {
        return array_values(array_filter($this->values, function (FilterValue $filterValue) {
            return $filterValue->isActive();
        }));
    }

    /**
     * @return string[]
     */
    public function getActiveArgumentValues(): array
    {
        return array_map(function (FilterValue $filterValue) {
            return $filterValue->getArgumentValue();
        }, $this->getActiveValues());
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

    public function isMm(): bool
    {
        return $this->mm;
    }

    public function setMm(bool $mm): self
    {
        $this->mm = $mm;

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
