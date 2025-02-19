# REMIND - Extbase Extension

This extension provides basic functionality that can be used in other extbase extensions, mainly configurations and utilities/services for Plugins
that allow consistent list, filter and detail views.

## Plugins

Plugins can have on of three types: Detail, FilterableList or SelectionList.
To configure a plugin using one of these types first register a plugin the usual way using `TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin` and then use the returned plugin signature along with the desired type and the table name to call `Remind\Extbase\Utility\PluginUtility::addTcaType`. These calls should happen in `tt_content.php`.

```php
$filterableListSignature = ExtensionUtility::registerPlugin(
    'Products',
    'FilterableList',
    'LLL:EXT:rmnd_products/Resources/Private/Language/locallang_tca.xlf:filterableList',
    'productsfilterablelist',
    'Products'
);

PluginUtility::addTcaType($filterableListSignature, PluginType::FILTERABLE_LIST, 'tx_products_domain_model_product');
```

## FilterValueMapper

Used to modify filter keys and check filter values. Filter query parameters use an array syntax like `?filter[name]=...&filter[title]=...` and the `FilterValueMapper` allows to change the array key by using aspects. In addition, only values defined in `pi_flexform` field of `tt_content` with `CType` defined in config are allowed for values. `parameters` and `aspects` act the same as in `ExtbaseQuery Route Enhancer` config.

Example for `?filter[name]=...&filter[title]=...`:

```yaml
aspects:
  filter:
    type: FilterValueMapper
    tableName: tx_contacts_domain_model_contact
    cType: contacts_filterablelist
    parameters:
      keys:
        name: Name
        title: titleKey
    aspects:
      titleKey:
        type: LocaleModifier
        default: Title
        localeMap:
          -
            locale: 'de_DE.*'
            value: Titel

```
With these settings, the query parameters will look like this:

English: `?filter[Name]=...&filter[Title]=...`

German: `?filter[Name]=...&filter[Titel]=...`
