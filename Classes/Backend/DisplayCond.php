<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend;

class DisplayCond
{
    /**
     * @param mixed[] $args
     */
    public function equalsFlexFormValue(array $args): bool
    {
        $record = $args['record'];
        $conditionParameters = $args['conditionParameters'];
        if (count($conditionParameters) > 1) {
            [$fieldName, $value] = $conditionParameters;
            $piFlexform = $record['pi_flexform'];
            $fieldValue = null;
            foreach ($piFlexform['data'] as $sheet) {
                $sheetSettings = $sheet['lDEF'];
                if (array_key_exists($fieldName, $sheetSettings)) {
                    $fieldValue = $sheetSettings[$fieldName]['vDEF'];
                    if (is_array($fieldValue)) {
                        $fieldValue = $fieldValue[0] ?? null;
                    }
                    continue;
                }
            }
            return $fieldValue === $value;
        } else {
            return true;
        }
    }
}
