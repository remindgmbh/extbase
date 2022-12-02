<?php

declare(strict_types=1);

use Remind\Extbase\Backend\Form\Element\FrontendFilterElement;
use Remind\Extbase\Backend\Form\Element\SelectMultipleSideBySideJsonElement;
use Remind\Extbase\Hooks\FlexFormHook;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;

defined('TYPO3_MODE') || die('Access denied.');

(function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1669984830] = [
        'nodeName' => 'frontendFilter',
        'priority' => 40,
        'class' => FrontendFilterElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1670313367] = [
        'nodeName' => 'selectMultipleSideBySideJson',
        'priority' => 40,
        'class' => SelectMultipleSideBySideJsonElement::class,
    ];

    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SC_OPTIONS']
        [FlexFormTools::class]
        ['flexParsing']
        [FlexFormHook::class] = FlexFormHook::class;
})();
