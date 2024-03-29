<?php

namespace Remind\Extbase\Domain\Repository;

use Remind\Extbase\Utility\Dto\Conjunction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FilterableRepository extends Repository
{
    /**
     * @param \Remind\Extbase\Utility\Dto\DatabaseFilter[] $filters
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
                        if (!$fieldValue) {
                            // $field contains the number of relations, so if $fieldValue is "" it should be 0
                            $fieldConstraints[] = $query->equals($field, 0);
                        } else {
                            $fieldConstraints[] = $query->contains($field, $fieldValue);
                        }
                    } else {
                        if (!$fieldValue) {
                            // if $value is empty (should be '' because query param cannot be null) either
                            // an empty string or null is allowed
                            $fieldConstraints[] = $query->logicalOr(
                                $query->equals($field, null),
                                $query->equals($field, ''),
                            );
                        } else {
                            $fieldConstraints[] = $query->equals($field, $fieldValue);
                        }
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
