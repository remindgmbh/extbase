<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use InvalidArgumentException;
use PDO;
use Remind\Extbase\FlexForms\FrontendFilterSheets;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\ContextAwareInterface;
use TYPO3\CMS\Core\Context\ContextAwareTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendGroupRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Routing\Aspect\AspectFactory;
use TYPO3\CMS\Core\Routing\Aspect\ModifiableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\PersistedMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\SiteAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Service\FlexFormService;
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
    private string $cType;
    private array $parameters;
    private array $aspects;
    private AspectFactory $aspectFactory;
    private FlexFormService $flexFormService;

    public function __construct(array $settings)
    {
        $tableName = $settings['tableName'] ?? null;
        $cType = $settings['cType'] ?? null;
        $parameters = $settings['parameters'] ?? [];
        $aspects = $settings['aspects'] ?? [];

        if (!is_string($tableName)) {
            throw new InvalidArgumentException(
                'tableName must be string',
                1674134308
            );
        }

        if (!is_string($cType)) {
            throw new InvalidArgumentException(
                'cType must be string',
                1678105306
            );
        }

        if (!is_array($parameters)) {
            throw new InvalidArgumentException(
                'parameters must be array',
                1674134532
            );
        }

        if (!is_array($aspects)) {
            throw new InvalidArgumentException(
                'aspects must be array',
                1678435905
            );
        }

        $this->tableName = $tableName;
        $this->cType = $cType;
        $this->parameters = $parameters;
        $this->aspects = $aspects;
        $this->aspectFactory = GeneralUtility::makeInstance(AspectFactory::class);
        $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
    }

    public function generate(string $originalValue): ?string
    {
        $parameterKeys = $this->getParameterKeys();
        $values = json_decode($originalValue, true);
        $result = [];
        foreach ($values as $fieldName => $value) {
            $generatedValue = $this->processValue($fieldName, $value, 'generate');
            $result[$parameterKeys[$fieldName] ?? $fieldName] = $generatedValue;
        }

        if (!$this->isValid($result)) {
            return null;
        }

        return json_encode($result);
    }

    public function resolve(string $originalValue): ?string
    {
        $parameterKeys = array_flip($this->getParameterKeys());
        $values = json_decode($originalValue, true);
        $result = [];
        foreach ($values as $mappedFieldName => $value) {
            $fieldName = $parameterKeys[$mappedFieldName] ?? $mappedFieldName;
            $resolvedValue = $this->processValue($fieldName, $value, 'resolve');
            $result[$fieldName] = $resolvedValue;
        }

        if (!$this->isValid($result)) {
            return null;
        }

        return json_encode($result);
    }

    private function isValid(array $values): bool
    {
        $filters = $this->getFilters();

        foreach ($filters as $filter) {
            $fields = GeneralUtility::trimExplode(
                ',',
                $filter[FrontendFilterSheets::FIELDS],
                true,
            );

            $valuesToCheck = [];

            foreach ($values as $key => $value) {
                if (in_array($key, $fields)) {
                    $valuesToCheck[$key] = $values[$key];
                    unset($values[$key]);
                }
            }

            if (!empty($valuesToCheck)) {
                $dynamicValues = (bool) ($filter[FrontendFilterSheets::DYNAMIC_VALUES] ?? null);

                if ($dynamicValues) {
                    if (
                        $this->isValueInJsonValues($filter[FrontendFilterSheets::EXCLUDED_VALUES], $valuesToCheck) ||
                        !$this->recordExists($valuesToCheck)
                    ) {
                        return false;
                    }
                } else {
                    if (!$this->isValueInJsonValues($filter[FrontendFilterSheets::VALUES], $valuesToCheck)) {
                        return false;
                    }
                }
            }

            if (empty($values)) {
                break;
            }
        }

        return true;
    }

    private function processValue(string $key, mixed $value, string $aspectFunction): mixed
    {
        $aspectName = $this->parameters['values'][$key] ?? null;
        $aspect = $this->aspects[$aspectName] ?? null;
        if (!$aspectName) {
            return $value;
        } elseif (!$aspect) {
            throw new InvalidArgumentException(
                sprintf(
                    'Aspect with name \'%s\' not found for parameter \'%s\'!',
                    $aspectName,
                    $key,
                ),
                1678436589
            );
        }
        [$aspect] = $this->aspectFactory->createAspects(
            [$aspect],
            $this->getSiteLanguage(),
            $this->getSite()
        );
        if (!($aspect instanceof StaticMappableAspectInterface)) {
            throw new InvalidArgumentException(
                sprintf(
                    'FilterValueMapper parameters/values aspects must be of type \'%s\'',
                    StaticMappableAspectInterface::class
                ),
                1678349946
            );
        }

        if (is_array($value)) {
            return array_map(function (mixed $value) use ($aspect, $aspectFunction) {
                return $aspect->{$aspectFunction}((string) $value);
            }, $value);
        } else {
            return $aspect->{$aspectFunction}((string) $value);
        }
    }

    private function getParameterKeys(): array
    {
        $result = [];
        foreach ($this->parameters['keys'] ?? [] as $fieldName => $parameter) {
            $aspect = $this->aspects[$parameter] ?? null;
            if (!$aspect) {
                $result[$fieldName] = $parameter;
            } else {
                [$modifier] = $this->aspectFactory->createAspects(
                    [$aspect],
                    $this->getSiteLanguage(),
                    $this->getSite()
                );
                if (!($modifier instanceof ModifiableAspectInterface)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'FilterValueMapper parameters/keys aspects must be of type \'%s\'',
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

    private function isValueInJsonValues(string $jsonValues, array $valueToCheck): bool
    {
        $values = array_map(function (string $jsonValue) {
            return json_decode($jsonValue, true);
        }, json_decode($jsonValues ?? '', true) ?? []);

        foreach ($values as $value) {
            $diff = array_diff_assoc($value, $valueToCheck);
            if (count($diff) === 0) {
                return true;
            }
        }

        return false;
    }

    private function recordExists(array $values): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->tableName)
            ->from($this->tableName);

        $constraints = [];
        foreach ($values as $field => $value) {
            $fieldTca = BackendUtility::getTcaFieldConfiguration($this->tableName, $field);
            $mmTable = $fieldTca['MM'] ?? null;

            if ($mmTable) {
                if (!$value) {
                    // $field contains the number of relations, so if $value is "" it should be 0
                    $constraints[] = $queryBuilder->expr()->eq($field, 0);
                } else {
                    $queryBuilder = $queryBuilder->join(
                        $this->tableName,
                        $mmTable,
                        $mmTable,
                        $queryBuilder->expr()->eq(
                            $mmTable . '.uid_local',
                            $queryBuilder->quoteIdentifier($this->tableName . '.uid')
                        ),
                    );
                    $constraints[] = $queryBuilder->expr()->eq(
                        $mmTable . '.uid_foreign',
                        $queryBuilder->createNamedParameter($value)
                    );
                }
            } else {
                if (!$value) {
                    // if $value is empty (should be '' because query param cannot be null) either
                    // an empty string or null is allowed
                    $constraints[] = $queryBuilder->expr()->or(
                        $queryBuilder->expr()->isNull($field),
                        $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter(''))
                    );
                } else {
                    $constraints[] = $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value));
                }
            }
        }

        $queryResult = $queryBuilder
            ->select('uid')
            ->where($queryBuilder->expr()->and(...$constraints))
            ->setMaxResults(1)
            ->executeQuery();
        return $queryResult->rowCount() > 0;
    }

    private function getFilters(): array
    {
        $l10nParent = $this->context->getPropertyFromAspect('page', 'l10n_parent');
        $pageUid = $l10nParent ? $l10nParent : $this->context->getPropertyFromAspect('page', 'uid');
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content')
            ->from('tt_content');
        $queryBuilder->setRestrictions(
            GeneralUtility::makeInstance(FrontendRestrictionContainer::class, $this->context)
        );
        $queryBuilder->getRestrictions()->removeByType(FrontendGroupRestriction::class);
        $queryBuilder
            ->select('pi_flexform')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter($pageUid, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'CType',
                        $queryBuilder->createNamedParameter($this->cType, PDO::PARAM_STR)
                    ),
                    $queryBuilder->expr()->eq(
                        'sys_language_uid',
                        $queryBuilder->createNamedParameter($this->siteLanguage->getLanguageId())
                    ),
                )
            );

        $flexFormString = $queryBuilder->executeQuery()->fetchOne();
        $flexForm = $this->flexFormService->convertFlexFormContentToArray($flexFormString);
        return array_map(function (array $filter) {
            return $filter[FrontendFilterSheets::FILTER];
        }, $flexForm['settings'][FrontendFilterSheets::FILTERS]);
    }
}
