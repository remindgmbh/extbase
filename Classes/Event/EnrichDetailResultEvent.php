<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use Remind\Extbase\Controller\Dto\DetailResult;

final class EnrichDetailResultEvent extends AbstractExtbaseEvent
{
    private DetailResult $detailResult;

    public function __construct(
        string $extensionName,
        DetailResult $detailResult,
    ) {
        parent::__construct($extensionName);
        $this->detailResult = $detailResult;
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
}
