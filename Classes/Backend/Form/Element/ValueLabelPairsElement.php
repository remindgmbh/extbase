<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class ValueLabelPairsElement extends AbstractFormElement
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
            [$label, $value] = $item;
            return [
                'value' => $value,
                'label' => $this->appendValueToLabelInDebugMode($label, $value),
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
                '<typo3-backend-value-label-pairs-element %s></typo3-backend-value-label-pairs-element>',
                GeneralUtility::implodeAttributes([
                    'dataId' => $attributes['id'],
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

        $resultArray['requireJsModules'][] = JavaScriptModuleInstruction::forRequireJS(
            'TYPO3/CMS/RmndExtbase/Backend/Element/ValueLabelPairsElement'
        );
        $resultArray
            ['additionalInlineLanguageLabelFiles'][] = 'EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf';

        $resultArray['html'] = implode(LF, $html);
        return $resultArray;
    }
}