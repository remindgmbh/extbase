<?php

declare(strict_types=1);

namespace Remind\Extbase\Domain\Repository;

use Remind\Extbase\Utility\Dto\Conjunction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @template-extends Repository<\TYPO3\CMS\Extbase\DomainObject\AbstractEntity>
 */
class FilterableRepository extends Repository
{
    /**
     * @param \Remind\Extbase\Utility\Dto\DatabaseFilter[] $filters
     * @return QueryResultInterface<\TYPO3\CMS\Extbase\DomainObject\AbstractEntity>
     */
    public function findByFilters(
        array $filters,
        ?int $limit = null,
        ?string $orderBy = null,
        ?string $orderDirection = null
    ): QueryResultInterface {
        $query = $this->createQuery();
        /** @var \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface[] $constraints */
        $constraints = [];
        foreach ($filters as $filter) {
            $values = $filter->getValues();
            $fields = GeneralUtility::trimExplode(',', $filter->getFilterName(), true);
            $filterConstraints = [];

            foreach ($values as $value) {
                $fieldConstraints = [];
                foreach ($fields as $field) {
                    $fieldValue = $value[$field];
                    if ($filter->isMm()) {
                        // $field contains the number of relations, so if $fieldValue is "" it should be 0
                        $fieldConstraints[] = !$fieldValue ? $query->equals($field, 0) : $query->contains($field, $fieldValue);
                    } else {
                        // if $value is empty (should be '' because query param cannot be null) either
                        // an empty string or null is allowed
                        $fieldConstraints[] = !$fieldValue ? $query->logicalOr(
                            $query->equals($field, null),
                            $query->equals($field, ''),
                        ) : $query->equals($field, $fieldValue);
                    }
                }
                if (!empty($fieldConstraints)) {
                    $filterConstraints[] = $query->logicalAnd(...$fieldConstraints);
                }
            }

            if (!empty($filterConstraints)) {
                switch ($filter->getConjunction()) {
                    case Conjunction::AND:
                        $constraints[] = $query->logicalAnd(...$filterConstraints);
                        break;
                    case Conjunction::OR:
                        $constraints[] = $query->logicalOr(...$filterConstraints);
                        break;
                }
            }
        }

        if (!empty($constraints)) {
            $query->matching($query->logicalAnd(...$constraints));
        }

        if ($orderBy) {
            $query->setOrderings([$orderBy => $orderDirection ?? QueryInterface::ORDER_ASCENDING]);
        }

        if ($limit) {
            $query->setLimit($limit);
        }

        return $query->execute();
    }
}
