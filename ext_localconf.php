<?php

declare(strict_types=1);

use Remind\Extbase\Backend\Form\Container\FlexFormContainerContainer;
use Remind\Extbase\Backend\Form\Element\ValueLabelPairsElement;
use Remind\Extbase\Backend\Form\FormDataProvider\ValueLabelPairsItems;
use Remind\Extbase\Routing\Aspect\FilterValueMapper;
use Remind\Extbase\Routing\Aspect\PersistedValueMapper;
use Remind\Extbase\Routing\Enhancer\QueryExtbasePluginEnhancer;
use TYPO3\CMS\Backend\Form\Container\FlexFormContainerContainer as BaseFlexFormContainerContainer;
use TYPO3\CMS\Backend\Form\FormDataProvider\SiteResolving;

defined('TYPO3_MODE') || die('Access denied.');

(function () {
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
        ['formDataGroup']
        ['flexFormSegment']
        [ValueLabelPairsItems::class]
        = [
            'depends' => [
                SiteResolving::class,
            ],
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
        ['QueryExtbase'] = QueryExtbasePluginEnhancer::class;
})();
