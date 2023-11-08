<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

final class ModifyDetailPageTitleEvent extends AbstractExtbaseEvent
{
    private AbstractEntity $entity;
    private string $title = '';

    public function __construct(string $extensionName, AbstractEntity $entity)
    {
        parent::__construct($extensionName);
        $this->entity = $entity;
    }

    public function getEntity(): AbstractEntity
    {
        return $this->entity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
