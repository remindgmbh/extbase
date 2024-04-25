<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class SelectMultipleSideBySideJsonElement extends AbstractFormElement
{
    /**
     * Default field information enabled for this element.
     *
     * @var array
     */
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    public function render()
    {
        $parameterArray = $this->data['parameterArray'];
        $resultArray = $this->initializeResultArray();

        $itemValue = $parameterArray['itemFormElValue'];
        $config = $parameterArray['fieldConf']['config'];

        $fieldId = StringUtility::getUniqueId('formengine-textarea-');
        $availableOptionsFieldId = StringUtility::getUniqueId('select-multiple-side-by-side-json-');

        $attributes = array_merge(
            [
                'id' => $fieldId,
                'name' => htmlspecialchars($parameterArray['itemFormElName']),
                'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
                'data-formengine-input-name' => htmlspecialchars($parameterArray['itemFormElName']),
                'wrap' => (string)(($config['wrap'] ?? 'virtual') ?: 'virtual'),
                'hidden' => 'true',
                'class' => implode(' ', [
                    'form-control',
                    't3js-formengine-textarea',
                    'formengine-textarea',
                ]),
            ],
            $this->getOnFieldChangeAttrs('change', $parameterArray['fieldChangeFunc'] ?? [])
        );

        $possibleItems = $config['items'] ?? [];
        $possibleItems = array_map(function ($item) {
            $label = $item['label'];
            $value = $item['value'];
            return [
                'value' => $value,
                'label' => $this->appendValueToLabelInDebugMode(
                    $label
                        ? $label
                        : LocalizationUtility::translate('emptyValue', 'rmnd_extbase'),
                    $value
                ),
            ];
        }, $possibleItems);

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $html = [
            '<div class="formengine-field-item t3js-formengine-field-item">',
            $fieldInformationHtml,
            '<div class="form-control-wrap">',
            '<div class="form-wizards-wrap">',
            sprintf(
                '<typo3-backend-select-multiple-side-by-side-json-element %s></typo3-backend-select-multiple-side-by-side-json-element>',
                GeneralUtility::implodeAttributes([
                    'dataId' => $attributes['id'],
                    'availableOptionsId' => $availableOptionsFieldId,
                    'possibleItems' => json_encode($possibleItems ?? []),
                ], true),
            ),
            sprintf(
                '<textarea %s>%s</textarea>',
                GeneralUtility::implodeAttributes($attributes, true),
                $itemValue
            ),
            '</div>',
            '</div>',
            '</div>',
        ];

        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@remind/extbase/backend/element/select-multiple-side-by-side-json-element.js'
        );
        $resultArray
            ['additionalInlineLanguageLabelFiles'][] = 'EXT:core/Resources/Private/Language/locallang_core.xlf';

        $resultArray['html'] = implode(LF, $html);
        return $resultArray;
    }

    protected function appendValueToLabelInDebugMode(string|int $label, string|int $value): string
    {
        if ($value !== '' && $this->getBackendUser()->shallDisplayDebugInformation() && $value !== $label) {
            return trim($label . ' [' . $value . ']');
        }

        return trim((string)$label);
    }
}
