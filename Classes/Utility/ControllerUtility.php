<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\Controller\Dto\Property;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ControllerUtility
{
    /**
     * @param mixed[] $propertyNames
     * @param mixed[] $propertyOverrides
     * @return Property[]
     */
    public static function getProperties(array $propertyNames, array $propertyOverrides, string $tableName): array
    {
        return array_map(function (string $property) use ($propertyOverrides, $tableName) {
            // Overrides currently only work for properties with single fields
            $propertyOverride = $propertyOverrides[$property] ?? null;
            $valueOverrides = $propertyOverride?->getOverrides() ?? [];
            $valueOverrides = array_reduce(
                array_keys($valueOverrides),
                function (array $result, int|string $jsonValue) use ($valueOverrides) {
                    $value = json_decode((string) $jsonValue, true);
                    if (count($value) === 1) {
                        $label = $valueOverrides[$jsonValue];
                        $key = array_key_first($value);
                        $result[$value[$key]] = $label;
                    }
                    return $result;
                },
                []
            );
            return new Property(
                GeneralUtility::underscoredToLowerCamelCase($property),
                self::getFieldLabel($property, $propertyOverrides, $tableName),
                $propertyOverride?->getPrefix() ?? '',
                $propertyOverride?->getSuffix() ?? '',
                $valueOverrides,
            );
        }, $propertyNames);
    }

    /**
     * @param Property[] $propertyOverrides
     */
    public static function getFieldLabel(string $field, array $propertyOverrides, string $tableName): string
    {
        $propertyOverride = $propertyOverrides[$field] ?? null;
        $label = $propertyOverride?->getLabel() ?? null;
        if (!$label) {
            $fields = GeneralUtility::trimExplode(',', $field, true);
            $labels = array_map(function (string $field) use ($tableName) {
                $label = BackendUtility::getItemLabel($tableName, $field) ?? '';
                return str_starts_with($label, 'LLL:') ? LocalizationUtility::translate($label) : $label;
            }, $fields);
            $label = implode(', ', $labels);
        }
        return $label;
    }
}
