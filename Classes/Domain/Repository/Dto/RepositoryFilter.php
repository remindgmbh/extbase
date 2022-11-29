<?php

namespace Remind\Extbase\Domain\Repository\Dto;

class RepositoryFilter
{
    private string $fieldName = '';

    /**
     * @var array<int|string> $values
     */
    private array $values;

    private bool $mm = false;

    private Conjunction $conjunction;

    public function __construct(string $fieldName, array $values, bool $mm, Conjunction $conjunction)
    {
        $this->fieldName = $fieldName;
        $this->values = $values;
        $this->mm = $mm;
        $this->conjunction = $conjunction;
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
