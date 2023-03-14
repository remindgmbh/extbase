<?php

declare(strict_types=1);

namespace Remind\Extbase\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class FieldValuesEditorController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
    ) {
    }

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $dataId = $queryParams['dataId'];
        $fields = $queryParams['fields'];
        $index = $queryParams['index'];
        $value = base64_decode($queryParams['value']);
        $fields = base64_decode($fields);
        $this->pageRenderer->setTitle(
            LocalizationUtility::translate(
                'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:fieldValuesEditor'
            )
        );
        $this->pageRenderer->addCssFile('EXT:backend/Resources/Public/Css/backend.css');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/RmndExtbase/Backend/FieldValuesEditor');
        $this->pageRenderer->addBodyContent(
            sprintf(
                '<typo3-backend-field-values-editor %s></typo3-backend-field-values-editor>',
                GeneralUtility::implodeAttributes([
                    'dataId' => $dataId,
                    'fields' => $fields,
                    'index' => $index,
                    'value' => $value,
                ], true),
            )
        );
        $content = $this->pageRenderer->render();
        return new HtmlResponse($content);
    }
}
