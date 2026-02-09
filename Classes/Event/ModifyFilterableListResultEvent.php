<?php

declare(strict_types=1);

namespace Remind\Extbase\Event;

use Remind\Extbase\Controller\Dto\FilterableListResult;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

final class ModifyFilterableListResultEvent extends AbstractExtbaseEvent
{
    private FilterableListResult $filterableListResult;

    private UriBuilder $uriBuilder;

    public function __construct(
        string $extensionName,
        FilterableListResult $filterableListResult,
        UriBuilder $uriBuilder,
    ) {
        parent::__construct($extensionName);
        $this->filterableListResult = $filterableListResult;
        $this->uriBuilder = $uriBuilder;
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

    public function getUriBuilder(): UriBuilder
    {
        return $this->uriBuilder;
    }
}
