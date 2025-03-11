<?php

declare(strict_types=1);

namespace Remind\Extbase\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
class CustomValueEditorController
{
    public const ROUTE = 'rmnd_custom_value_editor';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
    ) {
    }

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $dataId = $queryParams['dataId'];
        $props = $queryParams['props'];
        $index = $queryParams['index'];
        $value = $queryParams['value'];
        $this->pageRenderer->setTitle(
            LocalizationUtility::translate(
                'LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:customValueEditor'
            ) ?? ''
        );
        $this->pageRenderer->addCssFile('EXT:backend/Resources/Public/Css/backend.css');
        $this->pageRenderer->loadJavaScriptModule('@remind/extbase/backend/helper/custom-value-editor.js');
        $this->pageRenderer->addBodyContent(
            sprintf(
                '<typo3-backend-custom-value-editor %s></typo3-backend-custom-value-editor>',
                GeneralUtility::implodeAttributes([
                    'dataId' => $dataId,
                    'index' => $index,
                    'props' => $props,
                    'value' => $value,
                ], true),
            )
        );
        $content = $this->pageRenderer->render();
        return new HtmlResponse($content);
    }
}
