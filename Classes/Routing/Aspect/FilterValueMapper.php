<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use Remind\Extbase\Utility\FilterUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Routing\Aspect\PersistedAliasMapper;

class FilterValueMapper extends PersistedAliasMapper
{
    public function __construct(array $settings)
    {
        $settings['routeFieldName'] = '';
        parent::__construct($settings);
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function generate(string $value): ?string
    {
        return $this->mapValue($value);
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function resolve(string $value): ?string
    {
        return $this->mapValue($value);
    }

    private function mapValue(string $originalValue): ?string
    {
        $array = json_decode($originalValue, true);
        $array = FilterUtility::normalizeQueryParameters($array);
        $arrayValues = [];
        array_walk_recursive($array, function ($value, $key) use (&$arrayValues) {
            if (!in_array($value, $arrayValues[$key] ?? [])) {
                $arrayValues[$key][] = $value;
            }
        });
        foreach ($arrayValues as $field => $values) {
            foreach ($values as $value) {
                if (!$this->exists($field, $value)) {
                    return null;
                }
            }
        }
        return $originalValue;
    }

    private function exists(string $fieldName, string $value): bool
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryResult = $queryBuilder
            ->select('uid')
            ->where($queryBuilder->expr()->eq(
                $fieldName,
                $queryBuilder->createNamedParameter($value, Connection::PARAM_STR)
            ))
            ->setMaxResults(1)
            ->executeQuery();
        return $queryResult->rowCount() > 0;
    }
}
