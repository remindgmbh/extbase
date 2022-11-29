<?php

namespace Remind\Extbase\Backend\Form\Element;

use TYPO3\CMS\Backend\Form\Element\TextTableElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ListElement extends TextTableElement
{
    public function render()
    {
        $resultArray = parent::render();
        $resultArray['requireJsModules'][] = JavaScriptModuleInstruction::forRequireJS(
            'TYPO3/CMS/RmndExtbase/Backend/Element/ListElement'
        );
        return $resultArray;
    }

    /**
     * Creates the HTML for the Table Wizard:
     *
     * @return string HTML for the table wizard
     */
    protected function getTableWizard(string $dataId): string
    {
        return sprintf(
            '<typo3-backend-list %s></typo3-backend-list>',
            GeneralUtility::implodeAttributes([
                'type' => 'input',
                'selector' => '#' . $dataId,
            ], true)
        );
    }
}
