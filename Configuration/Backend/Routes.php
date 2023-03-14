<?php

use Remind\Extbase\Controller\FieldValuesEditorController;

return [
    'rmnd-field-values-editor' => [
        'path' => '/rmnd/field-values-editor',
        'access' => 'public',
        'target' => FieldValuesEditorController::class . '::mainAction',
    ],
];
