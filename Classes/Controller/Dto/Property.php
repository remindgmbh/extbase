<?php

declare(strict_types=1);

namespace Remind\Extbase\Controller\Dto;

use JsonSerializable;

class Property implements JsonSerializable
{
    private string $name = '';

    private string $label = '';

    private string $prefix = '';

    private string $suffix = '';

    /**
     * @var mixed[]
     */
    private array $overrides = [];

    /**
     * @param mixed[] $overrides
     */
    public function __construct(
        string $name,
        string $label,
        string $prefix,
        string $suffix,
        array $overrides,
    ) {
        $this->label = $label;
        $this->name = $name;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->overrides = $overrides;
    }

    /**
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'name' => $this->name,
            'overrides' => $this->overrides,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
        ];
    }

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

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setSuffix(string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * @param mixed[] $override
     */
    public function addOverride(array $override): self
    {
        $this->overrides[] = $override;

        return $this;
    }
}
