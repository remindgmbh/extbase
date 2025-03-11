<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\Controller\Dto\Property;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class ModelUtility
{
    /**
     * @param Property[] $properties
     * @return mixed[]
     */
    public static function getProcessedProperties(AbstractEntity $model, array $properties): array
    {
        return array_reduce($properties, function (array $result, Property $property) use ($model): array {
            $propertyName = $property->getName();
            $value = !empty($propertyName) ? $model->_getProperty($propertyName) : null;
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
