<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

use JsonSerializable;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class DetailResult implements JsonSerializable
{
    protected ?AbstractEntity $item = null;

    protected mixed $additionalData = null;

    /** @var Property[] $properties */
    protected ?array $properties = [];

    /**
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        return [
            'additionalData' => $this->additionalData,
            'item' => $this->item,
            'properties' => $this->properties,
        ];
    }

    public function getItem(): ?AbstractEntity
    {
        return $this->item;
    }

    public function setItem(?AbstractEntity $item): self
    {
        $this->item = $item;

        return $this;
    }

    /**
     * @return Property[]
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * @param Property[] $properties
     */
    public function setProperties(?array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    public function getAdditionalData(): mixed
    {
        return $this->additionalData;
    }

    public function setAdditionalData(mixed $additionalData): self
    {
        $this->additionalData = $additionalData;

        return $this;
    }
}
