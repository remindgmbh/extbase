<?php

declare(strict_types=1);

use Remind\Extbase\Backend\Form\Container\FlexFormContainerContainer;
use Remind\Extbase\Backend\Form\Element\SelectMultipleSideBySideJsonElement;
use Remind\Extbase\Backend\Form\Element\ValueLabelPairsElement;
use Remind\Extbase\Backend\Form\FormDataProvider\UserItemProvider;
use Remind\Extbase\Routing\Aspect\FilterValueMapper;
use TYPO3\CMS\Backend\Form\Container\FlexFormContainerContainer as BaseFlexFormContainerContainer;
use TYPO3\CMS\Backend\Form\FormDataProvider\SiteResolving;

defined('TYPO3') || die('Access denied.');

(function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['flexFormSegment'][UserItemProvider::class]
        = [
            'depends' => [
                SiteResolving::class,
            ],
        ];


    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1669984830]
        = [
            'class' => ValueLabelPairsElement::class,
            'nodeName' => 'valueLabelPairs',
            'priority' => 40,
        ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1694008464]
        = [
            'class' => SelectMultipleSideBySideJsonElement::class,
            'nodeName' => 'selectMultipleSideBySideJson',
            'priority' => 40,
        ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][BaseFlexFormContainerContainer::class]
        = [
            'className' => FlexFormContainerContainer::class,
        ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['FilterValueMapper'] = FilterValueMapper::class;
})();
