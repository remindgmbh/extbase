<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Headless\Service\FilesService;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

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
                $value = FileType::from($value->getOriginalResource()->getOriginalFile()->getType())->name === FileType::IMAGE->name
                    ? $this->filesService->processImage($value->getOriginalResource())
                    : $value;
            }
            $serialized[$property] = $value;
        }
        return $serialized;
    }
}
