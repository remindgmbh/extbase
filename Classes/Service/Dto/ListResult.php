<?php

declare(strict_types=1);

namespace Remind\Extbase\Service\Dto;

use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class ListResult
{
    protected ?QueryResultInterface $queryResult = null;

    protected ?PaginationInterface $pagination = null;

    protected int $count = 0;

    protected int $currentPage = 0;

    public function getQueryResult(): ?QueryResultInterface
    {
        return $this->queryResult;
    }

    public function setQueryResult(?QueryResultInterface $queryResult): self
    {
        $this->queryResult = $queryResult;

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

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $currentPage): self
    {
        $this->currentPage = $currentPage;

        return $this;
    }
}
