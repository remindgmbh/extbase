<?php

namespace Remind\Extbase\Backend\Form\Container;

use TYPO3\CMS\Backend\Form\Container\FlexFormContainerContainer as BaseFlexFormContainerContainer;

class FlexFormContainerContainer extends BaseFlexFormContainerContainer
{
    public function render()
    {
        $resultArray = parent::render();

        $html = explode(LF, $resultArray['html']);

        $dataStructure = $this->data['flexFormDataStructureArray'];
        $titleField = $dataStructure['titleField'] ?? null;
        $titleFieldAlt = $dataStructure['titleField_alt'] ?? null;

        $properTitleField = null;
        if (isset($dataStructure['el'][$titleField])) {
            $properTitleField = $titleField;
        } elseif (isset($dataStructure['el'][$titleFieldAlt])) {
            $properTitleField = $titleFieldAlt;
        }

        if ($properTitleField) {
            $rowData = $this->data['flexFormRowData'];
            $value = $rowData[$properTitleField]['vDEF'] ?? null;
            if ($value) {
                $index = current(array_keys(array_filter($html, function ($value) {
                    $value = trim($value);
                    return str_starts_with($value, '<div class="form-irre-header-cell form-irre-header-body">');
                })));
                $valueString = (is_array($value) ? implode(', ', $value) : $value);
                // Add value from field specified in titleField to container title
                $html[$index + 1] = $html[$index + 1] . ' [' . $valueString . ']';
                // hide content preview
                $html[$index + 2] = '<output class="content-preview" style="display: none"></output>';
            }
        }

        $resultArray['html'] = implode(LF, $html);

        return $resultArray;
    }
}
