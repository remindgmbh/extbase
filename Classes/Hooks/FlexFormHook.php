<?php

declare(strict_types=1);

namespace Remind\Extbase\Hooks;

use Remind\Extbase\FlexForms\ListFiltersSheets;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FlexFormHook
{
    // Used to translate composite strings since $GLOBALS['LANG'] is not available in TCA
    public function parseDataStructureByIdentifierPostProcess(array $dataStructure, array $identifier): array
    {
        if (isset($dataStructure['sheets'][ListFiltersSheets::BACKEND_SHEET_ID])) {
            if (is_array($dataStructure['sheets'][ListFiltersSheets::BACKEND_SHEET_ID]['ROOT']['el'])) {
                foreach ($dataStructure['sheets'][ListFiltersSheets::BACKEND_SHEET_ID]['ROOT']['el'] as $key => &$value) {
                    if (str_starts_with($key, 'settings.' . ListFiltersSheets::BACKEND_FILTERS)) {
                        $label = $this->getLabel($value['label']);
                        if (str_ends_with($key, '.' . ListFiltersSheets::CONJUNCTION)) {
                            $value['label'] = LocalizationUtility::translate(
                                'filters.backend.conjunction',
                                'rmnd_extbase',
                                [$label]
                            );
                        }
                        if (str_ends_with($key, '.' . ListFiltersSheets::VALUES)) {
                            $value['label'] = LocalizationUtility::translate(
                                'filters.backend.values',
                                'rmnd_extbase',
                                [$label]
                            );
                        }
                    }
                }
            }
        }
        if (isset($dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID])) {
            if (is_array($dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID]['ROOT']['el'])) {
                foreach ($dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID]['ROOT']['el'] as $key => &$value) {
                    if (
                        str_starts_with($key, 'settings.' . ListFiltersSheets::FRONTEND_FILTERS) &&
                        str_ends_with($key, '.' . ListFiltersSheets::VALUES)
                    ) {
                        $label = $this->getLabel($value['label']);
                        $value['label'] = LocalizationUtility::translate(
                            'filters.frontend.values',
                            'rmnd_extbase',
                            [$label]
                        );
                    }
                }
            }
        }
        return $dataStructure;
    }

    private function getLabel(string $label): string
    {
        if (str_starts_with($label, 'LLL:')) {
            $label = LocalizationUtility::translate($label);
        }
        return $label;
    }
}
