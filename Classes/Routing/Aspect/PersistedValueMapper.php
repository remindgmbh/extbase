<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Routing\Aspect\PersistedAliasMapper;

class PersistedValueMapper extends PersistedAliasMapper
{
    /**
     * @param string $value
     * @return null|string
     */
    public function generate(string $value): ?string
    {
        $exists = $this->exists($value);
        return $exists ? $value : null;
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function resolve(string $value): ?string
    {
        $exists = $this->exists($value);
        return $exists ? $value : null;
    }

    private function exists(string $value): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryResult = $queryBuilder
            ->select('uid')
            ->where($queryBuilder->expr()->eq(
                $this->routeFieldName,
                $queryBuilder->createNamedParameter($value, Connection::PARAM_STR)
            ))
            ->setMaxResults(1)
            ->executeQuery();
        return $queryResult->rowCount() > 0;
    }
}
