<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

use JsonSerializable;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class DetailResult implements JsonSerializable
{
    protected ?AbstractEntity $item = null;
    protected ?array $properties = [];
    protected mixed $additionalData = null;

    public function jsonSerialize(): array
    {
        return [
            'item' => $this->item,
            'properties' => $this->properties,
            'additionalData' => $this->additionalData,
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

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function setProperties(?array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    public function getAdditionalData(): mixed
    {
        return $this->additionalData;
    }

    public function setAdditionalData($additionalData): self
    {
        $this->additionalData = $additionalData;

        return $this;
    }
}
