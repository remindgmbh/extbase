<?php

declare(strict_types=1);

namespace Remind\Extbase\Backend\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProvider\AbstractItemProvider;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

class UserItemProvider extends AbstractItemProvider implements FormDataProviderInterface
{
    /**
     * @param mixed[] $result
     * @return mixed[]
     */
    public function addData(array $result): array
    {
        $table = $result['tableName'];

        foreach ($result['processedTca']['columns'] as $fieldName => $fieldConfig) {
            if (
                empty($fieldConfig['config']['type']) ||
                $fieldConfig['config']['type'] !== 'user' ||
                !in_array($fieldConfig['config']['renderType'], ['valueLabelPairs', 'selectMultipleSideBySideJson'])
            ) {
                continue;
            }

            $fieldConfig['config']['items'] = $this->sanitizeItemArray(
                $fieldConfig['config']['items'] ?? [],
                $table,
                $fieldName
            );

            // Resolve "itemsProcFunc"
            if (!empty($fieldConfig['config']['itemsProcFunc'])) {
                $fieldConfig['config']['items'] = $this->resolveItemProcessorFunction(
                    $result,
                    $fieldName,
                    $fieldConfig['config']['items']
                );
                // itemsProcFunc must not be used anymore
                unset($fieldConfig['config']['itemsProcFunc']);
            }

            if (!($fieldConfig['config']['skipTranslation'] ?? false)) {
                $fieldConfig['config']['items'] = $this->translateLabels(
                    $result,
                    $fieldConfig['config']['items'],
                    $table,
                    $fieldName,
                );
            }

            // itemProps are used to check if selected values are available and to create custom values
            $fieldConfig['config']['itemProps'] = $this->sanitizeItemArray(
                $fieldConfig['config']['itemProps'] ?? [],
                $table,
                $fieldName
            );

            // Resolve "itemPropsProcFunc"
            if (!empty($fieldConfig['config']['itemPropsProcFunc'])) {
                $resultCopy = $result;
                $resultCopy['processedTca']['columns'][$fieldName]['config']['itemsProcFunc'] = $fieldConfig['config']['itemPropsProcFunc'];
                $fieldConfig['config']['itemProps'] = $this->resolveItemProcessorFunction(
                    $resultCopy,
                    $fieldName,
                    $fieldConfig['config']['itemProps']
                );
                // itemPropsProcFunc must not be used anymore
                unset($fieldConfig['config']['itemPropsProcFunc']);

                $fieldConfig['config']['itemProps'] = $this->translateLabels(
                    $result,
                    $fieldConfig['config']['itemProps'],
                    $table,
                    $fieldName,
                );
            }

            $result['processedTca']['columns'][$fieldName] = $fieldConfig;
        }

        return $result;
    }
}
