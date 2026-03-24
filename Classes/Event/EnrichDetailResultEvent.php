<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use Remind\Extbase\Controller\Dto\DetailResult;

final class EnrichDetailResultEvent extends AbstractExtbaseEvent
{
    private DetailResult $detailResult;

    /**
     * @var mixed[]
     */
    private array $settings;

    /**
     * @param mixed[] $settings
     */
    public function __construct(
        string $extensionName,
        DetailResult $detailResult,
        array $settings,
    ) {
        parent::__construct($extensionName);
        $this->detailResult = $detailResult;
        $this->settings = $settings;
    }

    public function setAdditionalData(mixed $additionalData): self
    {
        $this->detailResult->setAdditionalData($additionalData);

        return $this;
    }

    public function getDetailResult(): DetailResult
    {
        return $this->detailResult;
    }

    /**
     * @return mixed[]
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param mixed[] $settings
     */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;

        return $this;
    }
}
