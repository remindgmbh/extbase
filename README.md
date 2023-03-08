# REMIND - Extbase Extension

This extension provides basic functionality that can be used in other extbase extensions, mainly configurations and utilities/services for Plugins
that allow consistent list, filter and detail views.

## ExtbaseQuery Route Enhancer

ExtbaseQuery Route Enhancer replaces extbase plugin query parameters with custom names and omits action and controller parameters.

### limitToPages
Required for ExtbaseQuery route enhancer to work, because without limit all routes would match.

### defaults
Behave the same as described [here](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Routing/AdvancedRoutingConfiguration.html#enhancers).

### namespace, extension, plugin, \_controller
Behave the same as described [here](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Routing/AdvancedRoutingConfiguration.html#extbase-plugin-enhancer).

### parameters
Parameters are divided into keys and values. In both, use the original parameter name as the key and the aspect name as the value. In keys it is possible to simply use a new parameter name as value instead of an aspect.

### aspects
Aspects used for keys and values defined in parameters. Aspects for parameter keys must implement `ModifiableAspectInterface` while aspects for parameter values must implement `MappableAspectInterface`.

### types
Limit the route enhancer to certain page types, for example to enhance solr search result routes but not autocomplete routes. Defaults to `[0]`.

### example for News Extension

```yaml
  News:
    limitToPages: [20]
    type: ExtbaseQuery
    extension: News
    plugin: Pi1
    _controller: 'News::list'
    defaults:
      page: '1'
    parameters:
      values:
        currentPage: pageValue
        overwriteDemand/categories: categoryValue
      keys:
        currentPage: page
        overwriteDemand/categories: categoryKey
    aspects:
      pageValue:
        type: StaticRangeMapper
        start: '1'
        end: '5'
      categoryValue:
        type: PersistedAliasMapper
        tableName: sys_category
        routeFieldName: slug
      categoryKey:
        type: LocaleModifier
        default: category
        localeMap:
          -
            locale: 'de_DE.*'
            value: kategorie

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
With this settings, the query parameters will look like this:

English: `?filter[Name]=...&filter[Title]=...`

German: `?filter[Name]=...&filter[Titel]=...`
