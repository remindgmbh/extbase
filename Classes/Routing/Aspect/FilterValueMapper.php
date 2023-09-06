<?php

declare(strict_types=1);

namespace Remind\Extbase\Routing\Aspect;

use InvalidArgumentException;
use PDO;
use Remind\Extbase\FlexForms\ListFiltersSheets;
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
        $filters = $this->getFilters();
        foreach ($values as $fieldName => $value) {
            if (!$this->isValid($value, $filters, $fieldName)) {
                return null;
            }
            $generatedValue = $this->processValue($fieldName, $value, 'generate');
            $result[$parameterKeys[$fieldName] ?? $fieldName] = $generatedValue;
        }

        return json_encode($result);
    }

    public function resolve(string $originalValue): ?string
    {
        $parameterKeys = array_flip($this->getParameterKeys());
        $values = json_decode($originalValue, true);
        $result = [];
        $filters = $this->getFilters();
        foreach ($values as $mappedFieldName => $value) {
            $fieldName = $parameterKeys[$mappedFieldName] ?? $mappedFieldName;
            $resolvedValue = $this->processValue($fieldName, $value, 'resolve');
            if (!$this->isValid($resolvedValue, $filters, $fieldName)) {
                return null;
            }
            $result[$fieldName] = $resolvedValue;
        }

        return json_encode($result);
    }

    private function isValid(mixed $value, array $filters, string $fieldName): bool
    {
        $filter = current(array_filter($filters, function (array $filter) use ($fieldName) {
            $allowMultipleFields = (bool) ($filter[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS] ?? false);
            $fields = GeneralUtility::trimExplode(
                ',',
                $filter[$allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD],
                true,
            );
            return in_array($fieldName, $fields);
        }));
        if (!$filter) {
            return false;
        }
        $jsonValues = GeneralUtility::trimExplode(',', $filter[ListFiltersSheets::AVAILABLE_VALUES]);
        $availableValues = array_reduce($jsonValues, function (array $result, string $jsonValue) use ($fieldName) {
            $value = json_decode($jsonValue, true);
            if (array_key_exists($fieldName, $value['value'])) {
                $result[] = $value['value'][$fieldName];
            }
            return $result;
        }, []);
        return empty(array_diff(is_array($value) ? $value : [$value], $availableValues));
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
                return $aspect->{$aspectFunction}($value);
            }, $value);
        } else {
            return $aspect->{$aspectFunction}($value);
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

    private function getFilters(): array
    {
        $pageUid = $this->context->getPropertyFromAspect('page', 'uid');
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
                    )
                )
            );

        $flexFormString = $queryBuilder->executeQuery()->fetchOne();
        $flexForm = $this->flexFormService->convertFlexFormContentToArray($flexFormString);
        return array_map(function (array $filter) {
            return $filter[ListFiltersSheets::FILTER];
        }, $flexForm['settings'][ListFiltersSheets::FILTERS]);
    }
}
