<?php

declare(strict_types=1);

namespace Remind\Extbase\Tests\Unit\Domain\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Extbase\Domain\Repository\FilterableRepository;
use Remind\Extbase\Utility\Dto\Conjunction;
use Remind\Extbase\Utility\Dto\DatabaseFilter;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\AndInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\OrInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(FilterableRepository::class)]
class FilterableRepositoryTest extends UnitTestCase
{
    #[Test]
    public function findByFiltersBuildsExpectedConstraintsForEmptyAndMmValues(): void
    {
        $queryResult = $this->createMock(QueryResultInterface::class);
        $comparison = $this->createMock(ComparisonInterface::class);
        $andConstraint = $this->createMock(AndInterface::class);
        $orConstraint = $this->createMock(OrInterface::class);

        $equalsCalls = [];
        $containsCalls = [];

        $query = $this->createMock(QueryInterface::class);
        $query->method('equals')->willReturnCallback(
            static function (string $field, mixed $value) use (&$equalsCalls, $comparison): ComparisonInterface {
                $equalsCalls[] = [$field, $value];
                return $comparison;
            }
        );
        $query->method('contains')->willReturnCallback(
            static function (string $field, mixed $value) use (&$containsCalls, $comparison): ComparisonInterface {
                $containsCalls[] = [$field, $value];
                return $comparison;
            }
        );
        $query->method('logicalOr')->willReturn($orConstraint);
        $query->method('logicalAnd')->willReturn($andConstraint);

        $query
            ->expects(self::once())
            ->method('matching')
            ->with($andConstraint);

        $query
            ->expects(self::once())
            ->method('execute')
            ->willReturn($queryResult);

        $repository = $this->getMockBuilder(FilterableRepository::class)
            ->onlyMethods(['createQuery'])
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->method('createQuery')
            ->willReturn($query);

        $filters = [
            new DatabaseFilter('title', [['title' => '']], false, Conjunction::OR),
            new DatabaseFilter('groups', [['groups' => ''], ['groups' => '5']], true, Conjunction::OR),
        ];

        $result = $repository->findByFilters($filters);

        self::assertSame($queryResult, $result);
        self::assertContains(['title', null], $equalsCalls);
        self::assertContains(['title', ''], $equalsCalls);
        self::assertContains(['groups', 0], $equalsCalls);
        self::assertContains(['groups', '5'], $containsCalls);
    }
}
