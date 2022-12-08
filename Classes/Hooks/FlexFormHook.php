<?php

declare(strict_types=1);

namespace Remind\Extbase\Hooks;

use Remind\Extbase\FlexForms\ListFiltersSheets;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
                        $label = $this->getLabel($key, $dataStructure);
                        $this->setLabel($value, $key, ListFiltersSheets::CONJUNCTION, 'filters.backend.conjunction', $label);
                        $this->setLabel($value, $key, ListFiltersSheets::VALUES, 'filters.backend.values', $label);
                    }
                }
            }
        }
        if (isset($dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID])) {
            if (is_array($dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID]['ROOT']['el'])) {
                foreach ($dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID]['ROOT']['el'] as $key => &$value) {
                    if (
                        str_starts_with($key, 'settings.' . ListFiltersSheets::FRONTEND_FILTERS) 
                    ) {
                        $label = $this->getLabel($key, $dataStructure);
                        $this->setLabel($value, $key, ListFiltersSheets::VALUES, 'filters.frontend.values', $label);
                        $this->setLabel($value, $key, ListFiltersSheets::EXCLUSIVE, 'filters.frontend.exclusive', $label);
                        $this->setLabel($value, $key, ListFiltersSheets::LABEL, 'filters.frontend.label', $label);
                    }
                }
            }
        }
        return $dataStructure;
    }

    private function setLabel(array &$value, string $key, string $setting, string $languageKey, string $label): void
    {
        if (str_ends_with($key, '.' . $setting)) {
            $value['label'] = LocalizationUtility::translate(
                $languageKey,
                'rmnd_extbase',
                [$label]
            );
        }
    }

    /**
     * Get Label from tca config by looking up field and table name in frontend filter values
     * @param string $key
     * @param array $dataStructure
     * @return string
     */
    private function getLabel(string $key, array $dataStructure): string
    {
        $array = $dataStructure['sheets'][ListFiltersSheets::FRONTEND_SHEET_ID]['ROOT']['el'];
        $key = str_replace('settings.' . ListFiltersSheets::BACKEND_FILTERS . '.', '', $key);
        $key = str_replace('settings.' . ListFiltersSheets::FRONTEND_FILTERS . '.', '', $key);
        $key = explode('.', $key)[0];
        $config = $array['settings.' . ListFiltersSheets::FRONTEND_FILTERS . '.' . $key . '.' . ListFiltersSheets::VALUES]['config'];
        $tableName = $config[ListFiltersSheets::TABLE_NAME];
        $fieldName = $config[ListFiltersSheets::FIELD_NAME];

        $label = BackendUtility::getItemLabel($tableName, $fieldName);

        if (str_starts_with($label, 'LLL:')) {
            $label = LocalizationUtility::translate($label);
        }
        return $label;
    }
}
