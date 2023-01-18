<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use Remind\Extbase\Utility\FilterUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
        $parameters = json_decode($originalValue, true);
        $normalizedParameters = FilterUtility::normalizeQueryParameters($parameters);

        foreach ($normalizedParameters as $field => $values) {
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
        $fieldTca = BackendUtility::getTcaFieldConfiguration($this->tableName, $fieldName);
        $mmTable = $fieldTca['MM'] ?? null;
        $foreignTable = $fieldTca['foreign_table'] ?? null;

        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('uid')
            ->setMaxResults(1);

        if ($foreignTable && $mmTable) {
            $queryBuilder
                ->join(
                    $this->tableName,
                    $mmTable,
                    $mmTable,
                    $queryBuilder->expr()->eq(
                        $mmTable . '.uid_local',
                        $queryBuilder->quoteIdentifier($this->tableName . '.uid')
                    )
                )
                ->where($queryBuilder->expr()->eq(
                    $mmTable . '.uid_foreign',
                    $queryBuilder->createNamedParameter($value)
                ));
        } else {
            $queryBuilder
                ->where($queryBuilder->expr()->eq(
                    $fieldName,
                    $queryBuilder->createNamedParameter($value)
                ));
        }

        $queryResult = $queryBuilder->executeQuery();
        return $queryResult->rowCount() > 0;
    }
}
