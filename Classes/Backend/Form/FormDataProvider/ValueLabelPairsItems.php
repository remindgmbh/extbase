<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProvider\AbstractItemProvider;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

class ValueLabelPairsItems extends AbstractItemProvider implements FormDataProviderInterface
{ /**
  * Add form data to result array
  *
  * @param array $result Initialized result array
  * @return array Result filled with more data
  */
    public function addData(array $result)
    {
        $table = $result['tableName'];

        foreach ($result['processedTca']['columns'] as $fieldName => $fieldConfig) {
            if (
                empty($fieldConfig['config']['type']) ||
                $fieldConfig['config']['type'] !== 'user' ||
                $fieldConfig['config']['renderType'] !== 'valueLabelPairs'
            ) {
                continue;
            }

            $fieldConfig['config']['items'] = $this->sanitizeItemArray($fieldConfig['config']['items'] ?? [], $table, $fieldName);

            // Resolve "itemsProcFunc"
            if (!empty($fieldConfig['config']['itemsProcFunc'])) {
                $fieldConfig['config']['items'] = $this->resolveItemProcessorFunction($result, $fieldName, $fieldConfig['config']['items']);
                // itemsProcFunc must not be used anymore
                unset($fieldConfig['config']['itemsProcFunc']);
            }

            $result['processedTca']['columns'][$fieldName] = $fieldConfig;
        }

        return $result;
    }
}