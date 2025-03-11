<?php

declare(strict_types=1);

namespace Remind\Extbase\Controller\Dto;

use TYPO3\CMS\Core\Pagination\PaginationInterface;

class ListResult
{
    /**
     * @var \Traversable<mixed, mixed>|array<mixed>|null
     */
    protected ?iterable $paginatedItems = null;

    protected ?PaginationInterface $pagination = null;

    protected int $count = 0;

    protected ?int $countWithoutLimit = null;

    protected int $currentPage = 0;

    /** @var Property[] $properties */
    protected ?array $properties = [];

    /**
     * @return \Traversable<mixed, mixed>|array<mixed>|null
     */
    public function getPaginatedItems(): ?iterable
    {
        return $this->paginatedItems;
    }

    /**
     * @param \Traversable<mixed, mixed>|array<mixed>|null $paginatedItems
     */
    public function setPaginatedItems(?iterable $paginatedItems): self
    {
        $this->paginatedItems = $paginatedItems;

        return $this;
    }

    public function getPagination(): ?PaginationInterface
    {
        return $this->pagination;
    }

    public function setPagination(?PaginationInterface $pagination): self
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getCountWithoutLimit(): ?int
    {
        return $this->countWithoutLimit;
    }

    public function setCountWithoutLimit(?int $countWithoutLimit): self
    {
        $this->countWithoutLimit = $countWithoutLimit;

        return $this;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $currentPage): self
    {
        $this->currentPage = $currentPage;

        return $this;
    }

    /**
     * @return Property[]
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * @param Property[] $properties
     */
    public function setProperties(?array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }
}
