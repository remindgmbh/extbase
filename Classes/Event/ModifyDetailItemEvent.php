<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

final class ModifyDetailItemEvent
{
    private string $extensionName;
    private string $source;
    private array $arguments;
    private ?AbstractEntity $result = null;

    public function __construct(
        string $extensionName,
        string $source,
        array $arguments,
    ) {
        $this->extensionName = $extensionName;
        $this->source = $source;
        $this->arguments = $arguments;
    }

    public function getExtensionName(): string
    {
        return $this->extensionName;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getResult(): ?AbstractEntity
    {
        return $this->result;
    }

    public function setResult(?AbstractEntity $result): self
    {
        $this->result = $result;

        return $this;
    }
}