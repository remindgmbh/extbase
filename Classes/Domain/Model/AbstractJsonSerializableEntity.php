<?php

declare(strict_types=1);

namespace Remind\Extbase\Domain\Model;

use FriendsOfTYPO3\Headless\Utility\FileUtility;
use JsonSerializable;
use Remind\Extbase\Service\Dto\Property;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

abstract class AbstractJsonSerializableEntity extends AbstractEntity implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        $classNames = [
            get_class($this),
            ...array_keys(class_parents($this)),
        ];

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
        $settings = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);
        $fileUtility = GeneralUtility::makeInstance(FileUtility::class);
        $result = [
            'uid' => $this->uid,
            'pid' => $this->pid,
        ];

        foreach ($classNames as $className) {
            if (isset($settings['jsonFields'][$className])) {
                $fields = $settings['jsonFields'][$className];
                foreach (GeneralUtility::trimExplode(',', $fields, true) as $field) {
                    if ($this->_hasProperty($field) || method_exists($this, 'get' . ucfirst($field))) {
                        if ($this->_hasProperty($field)) {
                            $value = $this->_getProperty($field);
                        } else {
                            $value = $this->{'get' . ucfirst($field)}();
                        }
                        if ($value instanceof FileReference) {
                            $value = $fileUtility->processFile($value->getOriginalResource());
                        }
                        if ($value instanceof ObjectStorage) {
                            $value = array_map(function (mixed $object) use ($fileUtility) {
                                if ($object instanceof FileReference) {
                                    return $fileUtility->processFile($object->getOriginalResource());
                                } else {
                                    return $object;
                                }
                            }, $value->toArray());
                        }
                        $result[$field] = $value;
                    }
                }
                return $result;
            }
        }
        return $result;
    }

    /**
     * @param Property[] $properties
     */
    public function getProcessedProperties(array $properties): array
    {
        return array_reduce($properties, function (array $result, Property $property) {
            $propertyName = $property->getName();
            $value = $this->_getProperty($propertyName);
            $field = GeneralUtility::camelCaseToLowerCaseUnderscored($propertyName);

            $valueOverrides = $property->getOverrides();
            $prefix = $property->getPrefix();
            $suffix = $property->getSuffix();

            $result[$field] = [
                'label' => $property->getLabel(),
                'value' => $valueOverrides[strval($value)] ?? $prefix . $value . $suffix,
            ];
            return $result;
        }, []);
    }
}
