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
    private array $parameters;
    private AspectFactory $aspectFactory;
    private FlexFormService $flexFormService;
    private string $cType;

    public function __construct(array $settings)
    {
        $tableName = $settings['tableName'] ?? null;
        $parameters = $settings['parameters'] ?? [];
        $cType = $settings['cType'] ?? null;

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

        if (!is_string($cType)) {
            throw new InvalidArgumentException(
                'cType must be string',
                1678105306
            );
        }

        $this->tableName = $tableName;
        $this->parameters = $parameters;
        $this->cType = $cType;
        $this->aspectFactory = GeneralUtility::makeInstance(AspectFactory::class);
        $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
    }

    public function generate(string $originalValue): ?string
    {
        $parametersMap = $this->getParametersMap();
        $values = json_decode($originalValue, true);
        $result = [];
        $filters = $this->getFilters();
        foreach ($values as $fieldName => $value) {
            if (!$this->isValid($value, $filters, $fieldName)) {
                return null;
            }
            $result[$parametersMap[$fieldName] ?? $fieldName] = $value;
        }

        return json_encode($result);
    }

    public function resolve(string $originalValue): ?string
    {
        $parametersMap = array_flip($this->getParametersMap());
        $values = json_decode($originalValue, true);
        $result = [];
        $filters = $this->getFilters();
        foreach ($values as $mappedFieldName => $value) {
            $fieldName = $parametersMap[$mappedFieldName] ?? $mappedFieldName;
            if (!$this->isValid($value, $filters, $fieldName)) {
                return null;
            }
            $result[$fieldName] = $value;
        }

        return json_encode($result);
    }

    private function isValid(mixed $value, array $filters, string $fieldName): bool
    {
        $filter = current(array_filter($filters, function (array $filter) use ($fieldName) {
            $allowMultipleFields = (bool) $filter[ListFiltersSheets::ALLOW_MULTIPLE_FIELDS];
            $fields = GeneralUtility::trimExplode(
                ',',
                $filter[$allowMultipleFields ? ListFiltersSheets::FIELDS : ListFiltersSheets::FIELD],
                true,
            );
            return in_array($fieldName, $fields);
        }));
        $availableValues = array_map(function (string $base64value) use ($fieldName) {
            return json_decode(base64_decode($base64value), true)['value'][$fieldName];
        }, GeneralUtility::trimExplode(',', $filter[ListFiltersSheets::AVAILABLE_VALUES]));
        return empty(array_diff(is_array($value) ? $value : [$value], $availableValues));
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

        $flexFormString = $queryBuilder->execute()->fetchOne();
        $flexForm = $this->flexFormService->convertFlexFormContentToArray($flexFormString);
        return array_map(function (array $filter) {
            return $filter[ListFiltersSheets::FILTER];
        }, $flexForm['settings'][ListFiltersSheets::FILTERS]);
    }
}
