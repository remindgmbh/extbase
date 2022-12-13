<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Element;

use Remind\Extbase\Backend\ItemsProc;
use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\Utility\BackendUtility;
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
        $databaseRow = $this->data['databaseRow'];
        $pages = array_map(function (array $page) {
            return $page['uid'];
        }, $databaseRow['pages']);
        $recursive = (int) $databaseRow['recursive'][0];

        $fieldName = $this->data['flexFormRowData'][ListFiltersSheets::FIELD]['vDEF'][0] ?? null;
        $tableName = $this->data
            ['flexFormDataStructureArray']
            [ListFiltersSheets::FIELD]
            ['config']
            [ItemsProc::PARAMETERS]
            [ItemsProc::PARAMETER_TABLE_NAME] ?? null;

        if ($fieldName && $tableName) {
            $possibleItems = BackendUtility::getAvailableValues(
                $tableName,
                $fieldName,
                $pages,
                $recursive
            );
            $possibleItems = array_map(function (array $item) {
                return [$item['label'] ?? $item['value'], $item['value']];
            }, $possibleItems);
        }
        $parameterArray['fieldConf']['config']['items'] = $possibleItems ?? [];
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
