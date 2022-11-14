<?php

namespace Remind\Extbase\Dto;

class ListFilter
{
    private bool $mm;

    private Conjunction $conjunction;

    private string $fieldName;

    private mixed $value;

    public function __construct(bool $mm, Conjunction $conjunction, string $fieldName, mixed $value)
    {
        $this->mm = $mm;
        $this->conjunction = $conjunction;
        $this->fieldName = $fieldName;
        $this->value = $value;
    }

    public function toArray()
    {
        return [
            'fieldName' => $this->fieldName,
            'value' => $this->value,
            'conjunction' => $this->conjunction,
            'mm' => $this->mm,
        ];
    }

    /**
     * Get the value of mm
     */
    public function isMm(): bool
    {
        return $this->mm;
    }

    /**
     * Set the value of mm
     */
    public function setMm(bool $mm): self
    {
        $this->mm = $mm;

        return $this;
    }

    /**
     * Get the value of conjunction
     */
    public function getConjunction(): Conjunction
    {
        return $this->conjunction;
    }

    /**
     * Set the value of conjunction
     */
    public function setConjunction(Conjunction $conjunction): self
    {
        $this->conjunction = $conjunction;

        return $this;
    }

    /**
     * Get the value of fieldName
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Set the value of fieldName
     */
    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    /**
     * Get the value of value
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set the value of value
     */
    public function setValue($value): self
    {
        $this->value = $value;

        return $this;
    }
}
