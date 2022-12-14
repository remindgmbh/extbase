<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\Dto\PluginType;
use Remind\Extbase\FlexForms\DetailDataSheet;
use Remind\Extbase\FlexForms\FilterSheet;
use Remind\Extbase\FlexForms\ListFilterSheet;
use Remind\Extbase\FlexForms\ListSheet;
use Remind\Extbase\FlexForms\SelectionDataSheet;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PluginUtility
{
    /**
     *  Add content element plugin to TCA types and add corresponding flex form sheets
     *  Has to be called in Configuration/TCA/*
     *
     *  @param string $type CType, e.g. for plugin registered with
     *                ExtensionUtility::registerPlugin('Contacts', 'FilterableList',...) the type
     *                would be 'contacts_filterablelist'
     *  @param PluginType $pluginType Type of plugin to determine flex form sheets to be added
     *  @param string $foreignTable only required for some flex form sheets as foreign_table parameter
     *                e.g. for contacts_detail to display available contacts the value
     *                would be tx_contacts_model_domain_contact
     *  @return void
     */
    public static function addTcaType(string $type, ?PluginType $pluginType = null, ?string $foreignTable = null)
    {
        $flexForm = self::getFlexFormByPluginType($type, $pluginType, $foreignTable);

        ExtensionManagementUtility::addPiFlexFormValue('*', $flexForm, $type);

        $columnOverrides = [];
        if (in_array($pluginType, [PluginType::DETAIL, PluginType::SELECTION_LIST])) {
            $columnOverrides = [
                'pages' => [
                    'onChange' => 'reload',
                ],
                'recursive' => [
                    'onChange' => 'reload',
                ],
            ];

            if ($pluginType === PluginType::DETAIL) {
                $displayCond = 'USER:Remind\\Extbase\\Backend\\DisplayCond->equalsFlexFormValue:settings.source:record';
                $columnOverrides['pages']['displayCond'] = $displayCond;
                $columnOverrides['recursive']['displayCond'] = $displayCond;
            }
        }

        $GLOBALS['TCA']['tt_content']['types'][$type] = [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    --palette--;;general,
                    --palette--;;headers,
                --div--;LLL:EXT:rmnd_extbase/Resources/Private/Language/locallang.xlf:settings,
                    pages,
                    recursive,
                    pi_flexform,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
                    --palette--;;frames,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    --palette--;;language,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;hidden,
                    --palette--;;access,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
                    categories,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
                    rowDescription,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
                    tx_headless_cookie_category,
                    tx_headless_cookie_message,
            ',
            'columnsOverrides' => $columnOverrides,
        ];
    }

    /**
     *  Add sources to detail view plugin, e.g. allows to show a contact on a page with a product detail plugin
     *  Must be called in ext_localconf.php
     *
     *  @param string $extensionName Name of the extension the detail source is added to,
     *                will be converted to lower case, e.g. 'contacts'
     *  @param string $pluginSignature Signature of the source plugin with tx_ prefix like used in
     *                extbase query parameters, e.g. 'tx_products_detail'
     *  @param string $repository Class path of the source repository the entity should be fetched from
     *                e.g. Vendor\Products\Domain\Repository\ProductRepository::class
     *  @param string $argument Name of the source argument like used in extbase query parameters, e.g. 'product'
     *  @return void
     */
    public static function addDetailSource(
        string $extensionName,
        string $pluginSignature,
        string $repository,
        string $argument,
        ?string $label = null
    ): void {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][strtolower($extensionName)]['detailSources'][$pluginSignature] = [
            'repository' => $repository,
            'argument' => $argument,
            'label' => $label,
        ];
    }

    public static function getDetailSources(string $extensionName): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][strtolower($extensionName)]['detailSources'] ?? [];
    }

    /**
     *  Add filter to filter plugin and filterable list plugin
     *  Must be called in ext_localconf.php
     *
     *  @param string $extensionName Name of the extension the filter is added to,
     *                will be converted to lower case, e.g. 'contacts'
     *  @param string $fieldName Name of the filter field
     *  @param string $label Filter label as shown in backend and frontend
     *  @param string $tableName used for available items in backend
     *  @param string $repository Class path of the repository the filter entity should be fetched from
     *  @return void
     */
    public static function addFilter(
        string $extensionName,
        string $fieldName,
        string $label,
        string $tableName,
        string $repository
    ): void {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][strtolower($extensionName)]['filter'][$fieldName] = [
            'label' => $label,
            'table' => $tableName,
            'repository' => $repository,
        ];
    }

    public static function getFilters(string $extensionName): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][strtolower($extensionName)]['filter'] ?? [];
    }

    /**
     * @param string $type plugin CType
     * @param array|string $dataStructure either a xml flexform file path, a xml flexform string or a flexform array
     */
    public static function addFlexForm(string $type, array|string $dataStructure): void
    {
        if (!is_array($dataStructure)) {
            // Taken from TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools
            if (strpos(trim($dataStructure), 'FILE:') === 0) {
                $file = GeneralUtility::getFileAbsFileName(substr(trim($dataStructure), 5));
                if (empty($file) || !@is_file($file)) {
                    throw new RuntimeException(
                        'Data structure file ' . $file . ' could not be resolved to an existing file',
                        1478105826
                    );
                }
                $dataStructure = (string) file_get_contents($file);
            }
            $dataStructure = GeneralUtility::xml2array($dataStructure);
        }

        self::mergeWithCurrentFlexForm($type, $dataStructure);
    }

    public static function createFlexFormFromSheets(array $sheets): array
    {
        return [
            'meta' => [
                'langDisable' => 1,
            ],
            'sheets' => $sheets,
        ];
    }

    private static function getCurrentFlexForm($type): array
    {
        $type = '*,' . $type;
        $flexFormString = $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$type];
        return GeneralUtility::xml2array($flexFormString);
    }

    private static function mergeWithCurrentFlexForm(string $type, array $newFlexFormArray): void
    {
        $currentFlexFormArray = self::getCurrentFlexForm($type);
        ArrayUtility::mergeRecursiveWithOverrule($currentFlexFormArray, $newFlexFormArray);
        $flexForm = self::flexFormArrayToString($currentFlexFormArray);
        ExtensionManagementUtility::addPiFlexFormValue('*', $flexForm, $type);
    }

    private static function getFlexFormByPluginType(
        string $type,
        ?PluginType $pluginType,
        ?string $tableName = null
    ): string {
        [$extensionName] = GeneralUtility::trimExplode('_', $type, true);

        $sheets = [];

        switch ($pluginType) {
            case PluginType::DETAIL:
                $sheets = DetailDataSheet::getSheet($extensionName, $tableName);
                break;
            case PluginType::FILTER:
                $sheets = FilterSheet::getSheet($extensionName);
                break;
            case PluginType::FILTERABLE_LIST:
                $sheets = ListSheet::getSheet();
                ArrayUtility::mergeRecursiveWithOverrule($sheets, ListFilterSheet::getSheet($extensionName));
                break;
            case PluginType::SELECTION_LIST:
                $sheets = ListSheet::getSheet();
                ArrayUtility::mergeRecursiveWithOverrule($sheets, SelectionDataSheet::getSheet($tableName));
                break;
            default:
                break;
        }

        $flexFormArray = self::createFlexFormFromSheets($sheets);

        return self::flexFormArrayToString($flexFormArray);
    }

    private static function flexFormArrayToString(array $flexForm): string
    {
        $flexFormTools = new FlexFormTools();
        return $flexFormTools->flexArray2Xml($flexForm, addPrologue: true);
    }
}
