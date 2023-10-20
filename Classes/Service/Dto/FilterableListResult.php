<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

class FilterableListResult extends ListResult
{
    /** @var FrontendFilter[] */
    protected array $frontendFilters = [];

    public function __construct(ListResult $listResult)
    {
        $this->setCount($listResult->getCount());
        $this->setCountWithoutLimit($listResult->getCountWithoutLimit());
        $this->setCurrentPage($listResult->getCurrentPage());
        $this->setPagination($listResult->getPagination());
        $this->setPaginatedItems($listResult->getPaginatedItems());
        $this->setProperties($listResult->getProperties());
    }

    /**
     * @return FrontendFilter[]
     */
    public function getFrontendFilters(): array
    {
        return $this->frontendFilters;
    }

    /**
     * @param FrontendFilter[] $frontendFilters
     */
    public function setFrontendFilters(array $frontendFilters): self
    {
        $this->frontendFilters = $frontendFilters;

        return $this;
    }
}
