<?php

declare(strict_types=1);

namespace Remind\Extbase\Dto;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class FilterData
{
    protected string $name = '';

    protected string $label = '';

    /** @var AbstractEntity[] $values */
    protected array $values = [];

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

    /**
     * @return AbstractEntity[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function addValue(AbstractEntity $value): self
    {
        $this->values[] = $value;

        return $this;
    }
}
