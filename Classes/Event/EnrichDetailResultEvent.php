<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use Remind\Extbase\Service\Dto\DetailResult;

final class EnrichDetailResultEvent
{
    private DetailResult $detailResult;

    public function __construct(
        DetailResult $detailResult,
    ) {
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
