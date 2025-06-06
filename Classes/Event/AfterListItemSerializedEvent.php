<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

final class AfterListItemSerializedEvent
{
    private AbstractEntity $item;

    /**
     * @var mixed[]
     */
    private array $serializedItem = [];

    /**
     * @param mixed[] $serializedItem
     */
    public function __construct(AbstractEntity $item, array $serializedItem)
    {
        $this->item = $item;
        $this->serializedItem = $serializedItem;
    }

    public function getItem(): AbstractEntity
    {
        return $this->item;
    }

    public function setItem(AbstractEntity $item): self
    {
        $this->item = $item;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getSerializedItem(): array
    {
        return $this->serializedItem;
    }

    /**
     * @param mixed[] $serializedItem
     */
    public function setSerializedItem(array $serializedItem): self
    {
        $this->serializedItem = $serializedItem;

        return $this;
    }
}
