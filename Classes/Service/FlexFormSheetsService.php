<?php

declare(strict_types=1);

namespace Remind\Extbase\Service;

use Remind\Extbase\FlexForms\PropertyOverrideSheets;
use Remind\Extbase\Service\Dto\Property;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlexFormSheetsService
{
    private DatabaseService $databaseService;
    public function __construct()
    {
        $this->databaseService = GeneralUtility::makeInstance(DatabaseService::class);
    }

    /**
     * @return Property[]
     */
    public function getPropertyOverrides(array $settings, int $sysLanguageUid): array
    {
        $propertyOverrides = $settings[PropertyOverrideSheets::OVERRIDES] ? $settings[PropertyOverrideSheets::OVERRIDES] : [];

        $contentElementId = $settings[PropertyOverrideSheets::REFERENCE] ? $settings[PropertyOverrideSheets::REFERENCE] : null;

        if ($contentElementId) {
            $propertyOverrides = [];

            $flexForm = $this->databaseService->getFlexFormByContentElementUid((int) $contentElementId, $sysLanguageUid);

            if (!empty($flexForm)) {
                $propertyOverrides = $flexForm['settings'][PropertyOverrideSheets::OVERRIDES] ?? [];
            }
        }

        return array_reduce($propertyOverrides, function (array $result, array $property) {
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
