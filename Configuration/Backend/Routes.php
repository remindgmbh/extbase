<?php

use Remind\Extbase\Controller\CustomValueEditorController;

return [
    CustomValueEditorController::ROUTE => [
        'path' => '/rmnd/custom-value-editor',
        'target' => CustomValueEditorController::class . '::mainAction',
    ],
];
