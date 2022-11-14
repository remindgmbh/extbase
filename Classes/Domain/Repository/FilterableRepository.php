<?php

namespace Remind\Extbase\Domain\Repository;

use Remind\Extbase\Dto\Conjunction;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FilterableRepository extends Repository
{
    /**
     * @param \Remind\Extbase\Dto\ListFilter[] $filters
     */
    public function findByFilters(
        array $filters,
        ?int $limit,
        ?string $orderBy,
        ?string $orderDirection
    ): QueryResultInterface {
        $query = $this->createQuery();
        /** @var \TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface[] $constraints */
        $constraints = [];
        foreach ($filters as $filter) {
            $value = $filter->getValue();
            $field = $filter->getFieldName();

            if (is_array($value)) {
                $arrayConstraints = [];
                foreach ($value as $arrayValue) {
                    if ($filter->isMm()) {
                        $arrayConstraints[] = $query->contains($field, $arrayValue);
                    } else {
                        $arrayConstraints[] = $query->equals($field, $arrayValue);
                    }
                }
                if (!empty($arrayConstraints)) {
                    switch ($filter->getConjunction()) {
                        case Conjunction::AND:
                            $constraints[] = $query->logicalAnd($arrayConstraints);
                            break;
                        case Conjunction::OR:
                            $constraints[] = $query->logicalOr($arrayConstraints);
                            break;
                    }
                }
            } else {
                if ($filter->isMm()) {
                    $constraints[] = $query->contains($field, $value);
                } else {
                    $constraints[] = $query->equals($field, $value);
                }
            }
        }

        if (!empty($constraints)) {
            $query->matching($query->logicalAnd($constraints));
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
