<?php

declare(strict_types=1);

use Remind\Extbase\Controller\CustomValueEditorController;

return [
    CustomValueEditorController::ROUTE => [
        'path' => '/rmnd/custom-value-editor',
        'target' => CustomValueEditorController::class . '::mainAction',
    ],
];
