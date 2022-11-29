<?php

namespace Remind\Extbase\Domain\Repository;

use Remind\Extbase\Domain\Repository\Dto\Conjunction;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FilterableRepository extends Repository
{
    /**
     * @param \Remind\Extbase\Domain\Repository\Dto\RepositoryFilter[] $filters
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
            $field = $filter->getFieldName();

            $arrayConstraints = [];
            foreach ($values as $value) {
                if ($filter->isMm()) {
                    $arrayConstraints[] = $query->contains($field, $value);
                } else {
                    $arrayConstraints[] = $query->equals($field, $value);
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
