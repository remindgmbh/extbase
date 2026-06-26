<?php

declare(strict_types=1);

namespace Remind\Extbase\Tests\Unit\Routing\Aspect;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Extbase\Routing\Aspect\FilterValueMapper;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(FilterValueMapper::class)]
class FilterValueMapperTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function constructorThrowsExceptionForInvalidTableNameType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1674134308);

        new FilterValueMapper([
            'aspects' => [],
            'parameters' => [],
            'tableName' => 123,
        ]);
    }

    #[Test]
    public function resolveThrowsExceptionIfConfiguredValueAspectDoesNotExist(): void
    {
        GeneralUtility::addInstance(
            TcaSchemaFactory::class,
            $this->createMock(TcaSchemaFactory::class)
        );

        $mapper = new FilterValueMapper([
            'aspects' => [],
            'parameters' => [
                'values' => [
                    'category' => 'missingAspect',
                ],
            ],
            'tableName' => 'tx_extbase_domain_model_demo',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1678436589);

        $mapper->resolve((string) json_encode(['category' => 'foo']));
    }
}
