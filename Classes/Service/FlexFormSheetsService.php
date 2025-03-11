<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Extbase\Controller\Dto\Property;
use Remind\Extbase\FlexForms\PropertyOverrideSheets;

class FlexFormSheetsService
{
    public function __construct(
        private readonly DatabaseService $databaseService,
    ) {
    }

    /**
     * @param mixed[] $settings
     * @return Property[]
     */
    public function getPropertyOverrides(array $settings, int $sysLanguageUid): array
    {
        $propertyOverrides = $settings[PropertyOverrideSheets::OVERRIDES] ?? null;

        $contentElementId = $settings[PropertyOverrideSheets::REFERENCE] ?? null;

        if ($contentElementId) {
            $propertyOverrides = [];

            $flexForm = $this->databaseService->getFlexFormByContentElementUid(
                (int) $contentElementId,
                $sysLanguageUid
            );

            if (!empty($flexForm)) {
                $propertyOverrides = $flexForm['settings'][PropertyOverrideSheets::OVERRIDES] ?? [];
            }
        }

        return array_reduce($propertyOverrides ? $propertyOverrides : [], function (array $result, array $property) {
            $property = $property[PropertyOverrideSheets::OVERRIDE];
            $valueOverrides = json_decode($property[PropertyOverrideSheets::VALUE_OVERRIDES], true) ?? [];

            $valueOverrides = array_reduce(
                $valueOverrides,
                function (array $result, array $valueOverride) {
                    $result[$valueOverride['value']] = $valueOverride['label'];
                    return $result;
                },
                []
            );

            $result[$property[PropertyOverrideSheets::FIELDS]] = new Property(
                $property[PropertyOverrideSheets::FIELDS],
                $property[PropertyOverrideSheets::LABEL],
                $property[PropertyOverrideSheets::VALUE_PREFIX],
                $property[PropertyOverrideSheets::VALUE_SUFFIX],
                $valueOverrides,
            );
            return $result;
        }, []);
    }
}
