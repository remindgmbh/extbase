<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Headless\Service\FilesService;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class SerializationService
{
    public function __construct(
        private readonly FilesService $filesService,
    ) {
    }

    /**
     * @param mixed[] $properties
     * @return mixed[]
     */
    public function serializeBaseProperties(AbstractEntity $entity, array $properties): array
    {
        $serialized = [];
        foreach ($properties as $property) {
            $getter = 'get' . ucfirst($property);
            if (!method_exists($entity, $getter)) {
                continue;
            }
            $value = $entity->{$getter}();
            if ($value instanceof FileReference) {
                $value = $this->filesService->processImage($value->getOriginalResource());
            }
            if ($value instanceof ObjectStorage) {
                $value = array_map(function ($item) {
                    if ($item instanceof FileReference) {
                        return $this->filesService->processImage($item->getOriginalResource());
                    }
                    return $item;
                }, $value->toArray());
            }
            $serialized[$property] = $value;
        }
        return $serialized;
    }
}
