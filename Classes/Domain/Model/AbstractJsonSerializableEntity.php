<?php

declare(strict_types=1);

namespace Remind\Extbase\Domain\Model;

use JsonSerializable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
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
        $result = [
            'uid' => $this->uid,
            'pid' => $this->pid,
        ];

        foreach ($classNames as $className) {
            if (isset($settings['jsonFields'][$className])) {
                $fields = $settings['jsonFields'][$className];
                foreach (GeneralUtility::trimExplode(',', $fields, true) as $field) {
                    $property = null;
                    if ($this->_hasProperty($field)) {
                        $property = $this->_getProperty($field);
                    } elseif (method_exists($this, 'get' . ucfirst($field))) {
                        $property = $this->{'get' . ucfirst($field)}();
                    }
                    if ($property) {
                        if ($property instanceof ObjectStorage) {
                            $property = $property->toArray();
                        }
                        $result[$field] = $property;
                    }
                }
                return $result;
            }
        }
        return $result;
    }
}
