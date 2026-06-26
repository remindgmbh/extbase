<?php

declare(strict_types=1);

namespace Remind\Extbase\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Extbase\FlexForms\PredefinedFilterSheets;
use Remind\Extbase\Utility\FilterUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(FilterUtility::class)]
class FilterUtilityTest extends UnitTestCase
{
    #[Test]
    public function getPredefinedDatabaseFiltersSkipsDisabledAndInvalidFilters(): void
    {
        $settings = [
            PredefinedFilterSheets::FILTERS => [
                [
                    PredefinedFilterSheets::FILTER => [
                        PredefinedFilterSheets::DISABLED => '1',
                        PredefinedFilterSheets::FIELDS => 'title',
                        PredefinedFilterSheets::VALUES => json_encode([
                            json_encode(['title' => 'A']),
                        ]),
                    ],
                ],
                [
                    PredefinedFilterSheets::FILTER => [
                        PredefinedFilterSheets::DISABLED => '0',
                        PredefinedFilterSheets::FIELDS => '',
                        PredefinedFilterSheets::VALUES => json_encode([
                            json_encode(['title' => 'B']),
                        ]),
                    ],
                ],
                [
                    PredefinedFilterSheets::FILTER => [
                        PredefinedFilterSheets::DISABLED => '0',
                        PredefinedFilterSheets::FIELDS => 'position',
                        PredefinedFilterSheets::VALUES => json_encode([]),
                    ],
                ],
            ],
        ];

        $result = FilterUtility::getPredefinedDatabaseFilters($settings, 'tx_extbase_domain_model_demo');

        self::assertSame([], $result);
    }

    #[Test]
    public function normalizeAndSimplifyQueryParametersWorkAsExpected(): void
    {
        $normalized = FilterUtility::normalizeQueryParameters([
            'category' => ['a', 'b'],
            'status' => 'active',
        ]);

        self::assertSame(
            [
                'category' => ['a', 'b'],
                'status' => ['active'],
            ],
            $normalized
        );

        $simplified = FilterUtility::simplifyQueryParameters([
            'category' => ['a', 'b'],
            'status' => ['active'],
        ]);

        self::assertSame(
            [
                'category' => ['a', 'b'],
                'status' => 'active',
            ],
            $simplified
        );
    }
}
