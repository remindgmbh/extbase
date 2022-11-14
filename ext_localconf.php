<?php

declare(strict_types=1);

use Remind\Extbase\Hooks\FlexFormHook;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;

defined('TYPO3_MODE') || die('Access denied.');

(function () {
    $GLOBALS
        ['TYPO3_CONF_VARS']
        ['SC_OPTIONS']
        [FlexFormTools::class]
        ['flexParsing']
        [FlexFormHook::class] = FlexFormHook::class;
})();
