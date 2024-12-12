<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\Element;

use Remind\Extbase\Controller\CustomValueEditorController;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ValueLabelPairsElement extends AbstractFormElement
{
    /**
     * @var mixed[]
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    /**
     * @return mixed[]
     */
    public function render(): array
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = $uriBuilder->buildUriFromRoute(CustomValueEditorController::ROUTE);
        $parameterArray = $this->data['parameterArray'];
        $resultArray = $this->initializeResultArray();

        $itemValue = $parameterArray['itemFormElValue'];
        $config = $parameterArray['fieldConf']['config'];

        $fieldId = StringUtility::getUniqueId('formengine-textarea-');

        $attributes = array_merge(
            [
                'class' => implode(' ', [
                    'form-control',
                    't3js-formengine-textarea',
                    'formengine-textarea',
                ]),
                'data-formengine-input-name' => htmlspecialchars($parameterArray['itemFormElName']),
                'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
                'hidden' => 'true',
                'id' => $fieldId,
                'name' => htmlspecialchars($parameterArray['itemFormElName']),
                'wrap' => (string)($config['wrap'] ?? 'virtual' ?: 'virtual'),
            ],
            $this->getOnFieldChangeAttrs('change', $parameterArray['fieldChangeFunc'] ?? [])
        );

        $languageId = intval($this->data['databaseRow']['sys_language_uid']);
        /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
        $site = $this->data['site'];
        $language = $site->getLanguageById($languageId);
        $languageCode = $language->getLocale()->getLanguageCode();

        $possibleItems = $config['items'] ?? [];
        $possibleItems = array_map(function ($item) use ($languageCode) {
            $labelKey = $item['label'];
            $value = $item['value'];
            return [
                'defaultLabel' => str_starts_with($labelKey, 'LLL:')
                    ? LocalizationUtility::translate($labelKey, null, null, $languageCode) ?? $labelKey
                    : $labelKey,
                'label' => $this->appendValueToLabelInDebugMode(
                    str_starts_with($labelKey, 'LLL:')
                        ? LocalizationUtility::translate($labelKey) ?? $labelKey
                        : $labelKey,
                    $value,
                ),
                'value' => $value,
            ];
        }, $possibleItems);
        $itemProps = $config['itemProps'] ?? [];
        $itemProps = array_map(function ($item) {
            $label = $item['label'];
            $value = $item['value'];
            return [
                'label' => $this->appendValueToLabelInDebugMode($label, $value),
                'value' => $value,
            ];
        }, $itemProps);

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
                    'customValueEditorUrl' => (string) $uri,
                    'dataId' => $attributes['id'],
                    'itemProps' => json_encode($itemProps) ?: '[]',
                    'possibleItems' => json_encode($possibleItems) ?: '[]',
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
            '@remind/extbase/backend/element/value-label-pairs-element.js'
        );
        $resultArray['additionalInlineLanguageLabelFiles'][] = 'EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf';
        $resultArray['additionalInlineLanguageLabelFiles'][] = 'EXT:core/Resources/Private/Language/locallang_core.xlf';

        $resultArray['html'] = implode(LF, $html);
        return $resultArray;
    }

    protected function appendValueToLabelInDebugMode(string|int $label, string|int $value): string
    {
        if (
            $value !== '' &&
            $this->getBackendUser()->shallDisplayDebugInformation() &&
            $value !== $label
        ) {
            return trim($label . ' [' . $value . ']');
        }

        return trim((string)$label);
    }
}
