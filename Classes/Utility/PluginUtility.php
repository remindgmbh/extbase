<?php

declare(strict_types=1);

namespace Remind\Extbase\Utility;

use Remind\Extbase\FlexForms\DetailSheets;
use Remind\Extbase\FlexForms\FrontendFilterSheets;
use Remind\Extbase\FlexForms\ListSheets;
use Remind\Extbase\FlexForms\PredefinedFilterSheets;
use Remind\Extbase\FlexForms\PropertyOverrideSheets;
use Remind\Extbase\FlexForms\SelectionSheets;
use Remind\Extbase\Utility\Dto\PluginType;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PluginUtility
{
    public const FILTER_FIELD_NAME = 'fieldName';
    public const FILTER_TABLE_NAME = 'tableName';
    private const TABLE_NAME = 'tableName';
    private const DETAIL_SOURCES = 'detailSources';
    private const LIST_ORDER_BY = 'listOrderBy';
    private const DISABLE_FILTER_COUNT = 'disableFilterCount';

    /**
     *  Add content element plugin to TCA types and add corresponding flex form sheets
     *  Must be called in Configuration/TCA/*
     *
     *  @param string $type CType, e.g. for plugin registered with
     *                ExtensionUtility::registerPlugin('Contacts', 'FilterableList',...) the type
     *                would be 'contacts_filterablelist'
     *  @param PluginType $pluginType Type of plugin to determine flex form sheets to be added
     *  @param string $tableName required for some flex form sheets as parameter
     *                e.g. for contacts_detail to display available contacts the value
     *                would be tx_contacts_model_domain_contact
     */
    public static function addTcaType(string $type, PluginType $pluginType, string $tableName): void
    {
        $flexForm = self::getFlexFormByPluginType($pluginType);

        ExtensionManagementUtility::addPiFlexFormValue('*', $flexForm, $type);

        $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'] = array_merge(
            $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'] ?? [],
            [
                $type => [
                    self::TABLE_NAME => $tableName,
                ],
            ]
        );

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

        $GLOBALS['TCA']['tt_content']['types'][$type] = [
            'columnsOverrides' => $columnOverrides,
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
        ];
    }

    public static function getTableName(string $type): string
    {
        return $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::TABLE_NAME] ?? '';
    }

    /**
     *  Add sources to detail view plugin, e.g. allows to show a contact on a page with a product detail plugin
     *  Must be called in Configuration/TCA/*
     *
     *  @param string $type CType the detail source is added to
     *  @param string $value Value of the detail source, can be used in Remind\Extbase\Event\DetailEntityModifierEvent
     *  @param string $label Label of the detail source, visible in Backend
     */
    public static function addDetailSource(
        string $type,
        string $value,
        ?string $label = null,
    ): void {
        $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::DETAIL_SOURCES][] = [
            'label' => $label ?? $value,
            'value' => $value,
        ];
    }

    /**
     * @return mixed[]
     */
    public static function getDetailSources(string $type): array
    {
        return $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::DETAIL_SOURCES] ?? [];
    }

    public static function addListOrderBy(
        string $type,
        string $value,
        ?string $label = null,
    ): void {
        $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::LIST_ORDER_BY][] = [
            'label' => $label ?? $value,
            'value' => $value,
        ];
    }

    /**
     * @return mixed[]
     */
    public static function getListOrderBy(string $type): array
    {
        return $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::LIST_ORDER_BY] ?? [];
    }

    public static function getDisableFilterCount(string $type): bool
    {
        return $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::DISABLE_FILTER_COUNT] ?? false;
    }

    public static function setDisableFilterCount(string $type, bool $value): void
    {
        $GLOBALS['TCA']['tt_content']['ctrl']['EXT']['rmnd_extbase'][$type][self::DISABLE_FILTER_COUNT] = $value;
    }

    /**
     * @param string $type plugin CType
     * @param mixed[]|string $dataStructure either a xml flexform file path, a xml flexform string or a flexform array
     */
    public static function addFlexForm(string $type, array|string $dataStructure): void
    {
        if (!is_array($dataStructure)) {
            // Taken from TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools
            if (strpos(trim($dataStructure), 'FILE:') === 0) {
                $file = GeneralUtility::getFileAbsFileName(substr(trim($dataStructure), 5));
                if (
                    empty($file) ||
                    !@is_file($file)
                ) {
                    throw new RuntimeException(
                        'Data structure file ' . $file . ' could not be resolved to an existing file',
                        1478105826
                    );
                }
                $dataStructure = (string) file_get_contents($file);
            }
            $dataStructure = GeneralUtility::xml2arrayProcess($dataStructure);
        }

        self::mergeWithCurrentFlexForm($type, $dataStructure);
    }

    /**
     * @return mixed[]
     */
    private static function getCurrentFlexForm(string $type): array
    {
        $type = '*,' . $type;
        $flexFormString = $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$type];
        return GeneralUtility::xml2arrayProcess($flexFormString);
    }

    /**
     * @param mixed[] $newFlexFormArray
     */
    private static function mergeWithCurrentFlexForm(string $type, array $newFlexFormArray): void
    {
        $currentFlexFormArray = self::getCurrentFlexForm($type);
        ArrayUtility::mergeRecursiveWithOverrule($currentFlexFormArray, $newFlexFormArray);
        $flexForm = self::flexFormArrayToString($currentFlexFormArray);
        ExtensionManagementUtility::addPiFlexFormValue('*', $flexForm, $type);
    }

    private static function getFlexFormByPluginType(PluginType $pluginType): string
    {
        $sheets = [];

        switch ($pluginType) {
            case PluginType::DETAIL:
                $sheets = array_replace(
                    DetailSheets::getSheets(),
                    PropertyOverrideSheets::getSheets(),
                );
                break;
            case PluginType::FILTERABLE_LIST:
                $sheets = array_replace(
                    ListSheets::getSheets(),
                    PredefinedFilterSheets::getSheets(),
                    FrontendFilterSheets::getSheets(),
                    PropertyOverrideSheets::getSheets(),
                );
                break;
            case PluginType::SELECTION_LIST:
                $sheets = array_replace(
                    ListSheets::getSheets(),
                    SelectionSheets::getSheets(),
                    PropertyOverrideSheets::getSheets(),
                );
                break;
            default:
                break;
        }

        $flexFormArray = [
            'meta' => [
                'langDisable' => 1,
            ],
            'sheets' => $sheets,
        ];

        return self::flexFormArrayToString($flexFormArray);
    }

    /**
     * @param mixed[] $flexForm
     */
    private static function flexFormArrayToString(array $flexForm): string
    {
        $flexFormTools = new FlexFormTools();
        return $flexFormTools->flexArray2Xml($flexForm, addPrologue: true);
    }
}
