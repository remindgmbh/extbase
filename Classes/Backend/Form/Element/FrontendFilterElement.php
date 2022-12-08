<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Element;

use Remind\Extbase\FlexForms\ListFiltersSheets;
use Remind\Extbase\Utility\BackendUtility as RemindBackendUtility;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class FrontendFilterElement extends AbstractFormElement
{
    public function render()
    {
        $parameterArray = $this->data['parameterArray'];
        $resultArray = $this->initializeResultArray();

        $itemValue = $parameterArray['itemFormElValue'];
        $config = $parameterArray['fieldConf']['config'];

        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

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

        $fieldControlResult = $this->renderFieldControl();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $databaseRow = $this->data['databaseRow'];
        $pages = array_map(function (array $page) {
            return $page['uid'];
        }, $databaseRow['pages']);
        $recursive = (int) $databaseRow['recursive'][0];

        $possibleItems = RemindBackendUtility::getAvailableValues(
            $config[ListFiltersSheets::TABLE_NAME],
            $config[ListFiltersSheets::FIELD_NAME],
            $pages,
            $recursive,
        );
        $possibleItems = array_map(function ($item) {
            $value = $item['value'];
            return [
                'value' => $value,
                'label' => $this->appendValueToLabelInDebugMode($item['label'] ?? $value, $value),
            ];
        }, $possibleItems);

        $html = [
            sprintf(
                '<typo3-backend-frontend-filter-element %s></typo3-backend-frontend-filter-element>',
                GeneralUtility::implodeAttributes([
                    'dataId' => $attributes['id'],
                    'possibleItems' => json_encode($possibleItems),
                ], true),
            ),
            sprintf(
                '<textarea %s>%s</textarea>',
                GeneralUtility::implodeAttributes($attributes, true),
                htmlspecialchars($itemValue, ENT_NOQUOTES)
            ),
        ];

        $resultArray['requireJsModules'][] = JavaScriptModuleInstruction::forRequireJS(
            'TYPO3/CMS/RmndExtbase/Backend/Element/FrontendFilterElement'
        );
        $resultArray
            ['additionalInlineLanguageLabelFiles'][] = 'EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf';

        $resultArray['html'] = implode(LF, $html);
        return $resultArray;
    }
}
