<?php

declare(strict_types=1);

use Remind\Extbase\Backend\Form\Container\FlexFormContainerContainer;
use Remind\Extbase\Backend\Form\Element\SelectMultipleSideBySideJsonElement;
use Remind\Extbase\Backend\Form\Element\ValueLabelPairsElement;
use Remind\Extbase\Routing\Aspect\FilterValueMapper;
use Remind\Extbase\Routing\Aspect\PersistedValueMapper;
use Remind\Extbase\Routing\Enhancer\ExtbasePluginQueryEnhancer;
use TYPO3\CMS\Backend\Form\Container\FlexFormContainerContainer as BaseFlexFormContainerContainer;
use TYPO3\CMS\Backend\Form\FormDataProvider\SiteResolving;
use Remind\Extbase\Backend\Form\FormDataProvider\SelectMultipleSideBySideJsonItems;
use Remind\Extbase\Backend\Form\FormDataProvider\UserItemProvider;

defined('TYPO3') || die('Access denied.');

(function () {
    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['formEngine']
        ['formDataGroup']
        ['flexFormSegment']
        [UserItemProvider::class]
        = [
            'depends' => [
                SiteResolving::class,
            ],
        ];


    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['formEngine']
        ['nodeRegistry']
        [1669984830]
        = [
            'nodeName' => 'valueLabelPairs',
            'priority' => 40,
            'class' => ValueLabelPairsElement::class,
        ];

    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['formEngine']
        ['nodeRegistry']
        [1694008464]
        = [
            'nodeName' => 'selectMultipleSideBySideJson',
            'priority' => 40,
            'class' => SelectMultipleSideBySideJsonElement::class,
        ];

    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['Objects']
        [BaseFlexFormContainerContainer::class]
        = [
            'className' => FlexFormContainerContainer::class,
        ];

    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['routing']
        ['aspects']
        ['PersistedValueMapper'] = PersistedValueMapper::class;

    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['routing']
        ['aspects']
        ['FilterValueMapper'] = FilterValueMapper::class;

    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SYS']
        ['routing']
        ['enhancers']
        ['ExtbaseQuery'] = ExtbasePluginQueryEnhancer::class;
})();
