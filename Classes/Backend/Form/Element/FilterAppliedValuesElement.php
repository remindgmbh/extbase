<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Element;

use Remind\Extbase\Utility\FilterUtility;
use TYPO3\CMS\Backend\Form\Element\SelectMultipleSideBySideElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

class FilterAppliedValuesElement extends SelectMultipleSideBySideElement
{
    public function render()
    {
        $parameterArray = &$this->data['parameterArray'];
        $elementName = $parameterArray['itemFormElName'];
        $selectedItems = json_decode($parameterArray['itemFormElValue'], true) ?? [];
        $parameterArray['itemFormElValue'] = $selectedItems;

        $flexFormContainerFieldName = $this->data['flexFormContainerFieldName'];
        $flexFormRowData = $this->data['flexFormRowData'];
        $currentValues = json_decode($flexFormRowData[$flexFormContainerFieldName]['vDEF'], true);

        $possibleItems = FilterUtility::getAvailableValues($this->data, $currentValues);
        $parameterArray['fieldConf']['config']['items'] = $possibleItems;
        $parameterArray['fieldConf']['config']['maxitems'] = PHP_INT_MAX;
        $resultArray = parent::render();
        $html = explode(LF, $resultArray['html']);
        $index = current(array_keys(array_filter($html, function ($value) use ($elementName) {
            $value = trim($value);
            return str_starts_with($value, '<input type="hidden" name="' . htmlspecialchars($elementName));
        })));
        $html[$index] = '<input ' .
            'type="hidden" data-separator="json" ' .
            'name="' . htmlspecialchars($elementName) . '" ' .
            'value="' . htmlspecialchars(json_encode($selectedItems)) . '" />';
        $resultArray['html'] = implode(LF, $html);

        $resultArray['requireJsModules'][] = JavaScriptModuleInstruction::forRequireJS(
            'TYPO3/CMS/RmndExtbase/Backend/Element/FilterAppliedValuesElement'
        );
        return $resultArray;
    }
}
