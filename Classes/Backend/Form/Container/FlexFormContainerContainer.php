<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Container;

use TYPO3\CMS\Backend\Form\Container\FlexFormContainerContainer as BaseFlexFormContainerContainer;

class FlexFormContainerContainer extends BaseFlexFormContainerContainer
{
    /**
     * @return mixed[]
     */
    public function render(): array
    {
        $resultArray = parent::render();

        $html = explode(LF, $resultArray['html']);

        $dataStructure = $this->data['flexFormDataStructureArray'];
        $titleField = $dataStructure['titleField'] ?? null;
        $titleFieldAlt = $dataStructure['titleField_alt'] ?? null;
        $disabledField = $dataStructure['disabledField'] ?? null;

        $rowData = $this->data['flexFormRowData'];

        $properTitleField = null;
        if (isset($dataStructure['el'][$titleField])) {
            $properTitleField = $titleField;
        } elseif (isset($dataStructure['el'][$titleFieldAlt])) {
            $properTitleField = $titleFieldAlt;
        }

        if ($properTitleField) {
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

        $disabled = (bool) ($rowData[$disabledField]['vDEF'] ?? false);

        if ($disabled) {
            $needle = 'class="';
            $pos = strpos($html[0], $needle) + strlen($needle);
            $html[0] = substr_replace($html[0], 'form-irre-object--hidden ', $pos, 0);
            $resultArray['stylesheetFiles'][] = 'EXT:rmnd_extbase/Resources/Public/Css/Backend/FlexFormContainerContainer.css';
        }

        $resultArray['html'] = implode(LF, $html);

        return $resultArray;
    }
}
