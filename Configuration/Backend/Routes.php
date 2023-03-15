<?php

use Remind\Extbase\Controller\FieldValuesEditorController;

return [
    'rmnd_field_values_editor' => [
        'path' => '/rmnd/field-values-editor',
        'target' => FieldValuesEditorController::class . '::mainAction',
    ],
];
