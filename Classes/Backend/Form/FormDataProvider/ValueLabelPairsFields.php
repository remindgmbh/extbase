<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProvider\AbstractItemProvider;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

class ValueLabelPairsFields extends AbstractItemProvider implements FormDataProviderInterface
{
   /**
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

            $fieldConfig['config']['fields'] = $this->sanitizeItemArray(
                $fieldConfig['config']['fields'] ?? [],
                $table,
                $fieldName
            );

            // Resolve "fieldsProcFunc"
            if (!empty($fieldConfig['config']['fieldsProcFunc'])) {
                $resultCopy = $result;
                $resultCopy['processedTca']['columns'][$fieldName]['config']['itemsProcFunc'] = $fieldConfig['config']['fieldsProcFunc'];
                $fieldConfig['config']['fields'] = $this->resolveItemProcessorFunction(
                    $resultCopy,
                    $fieldName,
                    $fieldConfig['config']['fields']
                );
                // fieldsProcFunc must not be used anymore
                unset($fieldConfig['config']['fieldsProcFunc']);
            }

            $result['processedTca']['columns'][$fieldName] = $fieldConfig;
        }

        return $result;
    }
}
