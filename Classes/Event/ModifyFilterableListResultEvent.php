<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use Remind\Extbase\Service\Dto\FilterableListResult;

final class ModifyFilterableListResultEvent extends AbstractExtbaseEvent
{
    private FilterableListResult $filterableListResult;

    public function __construct(
        string $extensionName,
        FilterableListResult $filterableListResult,
    ) {
        parent::__construct($extensionName);
        $this->filterableListResult = $filterableListResult;
    }

    public function getFilterableListResult(): FilterableListResult
    {
        return $this->filterableListResult;
    }

    public function setFilterableListResult(FilterableListResult $filterableListResult): self
    {
        $this->filterableListResult = $filterableListResult;

        return $this;
    }
}
