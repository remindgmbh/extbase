<?php

declare(strict_types=1);

use Remind\Extbase\Backend\Form\Container\FlexFormContainerContainer;
use Remind\Extbase\Backend\Form\Element\FilterAppliedValuesElement;
use Remind\Extbase\Backend\Form\Element\FilterAvailableValuesElement;
use TYPO3\CMS\Backend\Form\Container\FlexFormContainerContainer as BaseFlexFormContainerContainer;

defined('TYPO3_MODE') || die('Access denied.');

(function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1669984830] = [
        'nodeName' => 'filterAvailableValues',
        'priority' => 40,
        'class' => FilterAvailableValuesElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1670313367] = [
        'nodeName' => 'filterAppliedValues',
        'priority' => 40,
        'class' => FilterAppliedValuesElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][BaseFlexFormContainerContainer::class] = [
        'className' => FlexFormContainerContainer::class,
    ];
})();
