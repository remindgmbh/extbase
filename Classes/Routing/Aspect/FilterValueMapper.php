<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use InvalidArgumentException;
use Remind\Extbase\Utility\FilterUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\ContextAwareInterface;
use TYPO3\CMS\Core\Context\ContextAwareTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendGroupRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Routing\Aspect\AspectFactory;
use TYPO3\CMS\Core\Routing\Aspect\ModifiableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\PersistedMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\SiteAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\SiteAwareInterface;
use TYPO3\CMS\Core\Site\SiteLanguageAwareInterface;
use TYPO3\CMS\Core\Site\SiteLanguageAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FilterValueMapper implements
    PersistedMappableAspectInterface,
    StaticMappableAspectInterface,
    ContextAwareInterface,
    SiteLanguageAwareInterface,
    SiteAwareInterface
{
    use ContextAwareTrait;
    use SiteLanguageAwareTrait;
    use SiteAccessorTrait;

    private string $tableName;
    private array $parameters;
    private array $settings;
    private AspectFactory $aspectFactory;

    public function __construct(array $settings)
    {
        $tableName = $settings['tableName'] ?? null;
        $parameters = $settings['parameters'] ?? [];

        if (!is_string($tableName)) {
            throw new InvalidArgumentException(
                'tableName must be string',
                1674134308
            );
        }

        if (!is_array($parameters)) {
            throw new InvalidArgumentException(
                'parameters must be array',
                1674134532
            );
        }

        $this->settings = $settings;
        $this->tableName = $tableName;
        $this->parameters = $parameters;
        $this->aspectFactory = GeneralUtility::makeInstance(AspectFactory::class);
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function generate(string $originalValue): ?string
    {
        $originalParameters = $this->prepareParameters($originalValue);

        if (!$this->validateValues($originalParameters)) {
            return null;
        }

        $result = [];
        $parametersMap = $this->getParametersMap();

        foreach ($originalParameters as $field => $values) {
            $field = $parametersMap[$field] ?? $field;
            $result[$field] = $values;
        }

        $result = FilterUtility::simplifyQueryParameters($result);

        return json_encode($result);
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function resolve(string $originalValue): ?string
    {
        $originalParameters = $this->prepareParameters($originalValue);

        $result = [];
        $parametersMap = array_flip($this->getParametersMap());

        foreach ($originalParameters as $field => $values) {
            $field = $parametersMap[$field] ?? $field;
            $result[$field] = $values;
        }

        if (!$this->validateValues($result)) {
            return null;
        }

        $result = FilterUtility::simplifyQueryParameters($result);

        return json_encode($result);
    }

    private function validateValues(array $parameters): bool
    {
        foreach ($parameters as $field => $values) {
            foreach ($values as $value) {
                if (!$this->validateValue($field, $value)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function validateValue(string $fieldName, string $value): bool
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

    private function getParametersMap(): array
    {
        $result = [];
        foreach ($this->parameters as $fieldName => $parameter) {
            if (is_string($parameter)) {
                $result[$fieldName] = $parameter;
            } else {
                $aspects = $this->aspectFactory->createAspects(
                    [$parameter],
                    $this->getSiteLanguage(),
                    $this->getSite()
                );
                $modifier = current($aspects);
                if (!($modifier instanceof ModifiableAspectInterface)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'FilterValueMapper _arguments aspects must be of type \'%s\'',
                            ModifiableAspectInterface::class
                        ),
                        1674134317
                    );
                }
                $result[$fieldName] = $modifier->modify();
            }
        }
        return $result;
    }

    private function prepareParameters(string $value): array
    {
        $parameters = json_decode($value, true);
        return FilterUtility::normalizeQueryParameters($parameters);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->tableName)
            ->from($this->tableName);
        $queryBuilder->setRestrictions(
            GeneralUtility::makeInstance(FrontendRestrictionContainer::class, $this->context)
        );
        $queryBuilder->getRestrictions()->removeByType(FrontendGroupRestriction::class);
        return $queryBuilder;
    }
}
