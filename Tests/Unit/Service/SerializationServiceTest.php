<?php

declare(strict_types=1);

namespace Remind\Extbase\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Extbase\Service\SerializationService;
use Remind\Headless\Service\FilesService;
use stdClass;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(SerializationService::class)]
class SerializationServiceTest extends UnitTestCase
{
    #[Test]
    public function serializeBasePropertiesSkipsMissingGetterAndSerializesFileAndStorageValues(): void
    {
        $resource = $this->createMock(CoreFileReference::class);

        $fileReference = $this->createMock(FileReference::class);
        $fileReference
            ->expects(self::exactly(2))
            ->method('getOriginalResource')
            ->willReturn($resource);

        $storage = new ObjectStorage();
        $storage->attach($fileReference);
        $plainObject = new stdClass();
        $plainObject->value = 'plain-value';
        $storage->attach($plainObject);

        $entity = new class ($fileReference, $storage) extends AbstractEntity {
            /**
             * @param ObjectStorage<object> $files
             */
            public function __construct(
                private readonly FileReference $image,
                private readonly ObjectStorage $files,
            ) {
            }

            public function getTitle(): string
            {
                return 'Hello';
            }

            public function getImage(): FileReference
            {
                return $this->image;
            }

            /**
             * @return ObjectStorage<object>
             */
            public function getFiles(): ObjectStorage
            {
                return $this->files;
            }
        };

        $filesService = $this->createMock(FilesService::class);
        $filesService
            ->expects(self::exactly(2))
            ->method('processImage')
            ->with($resource)
            ->willReturnOnConsecutiveCalls(
                ['src' => 'processed-image'],
                ['src' => 'processed-storage-image']
            );

        $service = new SerializationService($filesService);

        $result = $service->serializeBaseProperties($entity, [
            'title',
            'image',
            'files',
            'missing',
        ]);

        self::assertSame('Hello', $result['title']);
        self::assertSame(['src' => 'processed-image'], $result['image']);
        self::assertSame([['src' => 'processed-storage-image'], $plainObject], $result['files']);
    }
}
