<?php

declare(strict_types=1);

namespace Remind\Extbase\Hooks;

use Remind\Extbase\FlexForms\FilterSheet;
use Remind\Extbase\FlexForms\ListFilterSheet;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FlexFormHook
{
    // Used to translate composite strings since $GLOBALS['LANG'] is not available in TCA
    public function parseDataStructureByIdentifierPostProcess(array $dataStructure, array $identifier): array
    {
        if (isset($dataStructure['sheets'][ListFilterSheet::SHEET_ID])) {
            foreach ($dataStructure['sheets'][ListFilterSheet::SHEET_ID]['ROOT']['el'] as $key => &$value) {
                if (str_starts_with($key, 'settings.' . ListFilterSheet::FILTER)) {
                    $label = LocalizationUtility::translate($value['label']);
                    if (str_ends_with($key, '.' . ListFilterSheet::CONJUNCTION)) {
                        $value['label'] = LocalizationUtility::translate('filter.mode', 'rmnd_extbase', [$label]);
                    }
                    if (str_ends_with($key, '.' . ListFilterSheet::VALUES)) {
                        $value['label'] = LocalizationUtility::translate(
                            'filter.list.values',
                            'rmnd_extbase',
                            [$label]
                        );
                    }
                }
            }
        }
        if (isset($dataStructure['sheets'][FilterSheet::SHEET_ID])) {
            foreach ($dataStructure['sheets'][FilterSheet::SHEET_ID]['ROOT']['el'] as $key => &$value) {
                if (
                    str_starts_with($key, 'settings.' . FilterSheet::FILTER) &&
                    str_ends_with($key, '.' . FilterSheet::VALUES)
                ) {
                    $label = LocalizationUtility::translate($value['label']);
                    $value['label'] = LocalizationUtility::translate('filter.values', 'rmnd_extbase', [$label]);
                }
            }
        }
        return $dataStructure;
    }
}
