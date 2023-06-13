<?php

namespace Remind\Extbase\Event;

use Remind\Extbase\Service\Dto\FilterableListResult;

final class ModifyFilterableListResultEvent
{
    private FilterableListResult $filterableListResult;

    public function __construct(
        FilterableListResult $filterableListResult,
    ) {
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
