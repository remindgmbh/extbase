# REMIND - Extbase Extension

This extension provides basic functionality that can be used in other extbase extensions, mainly configurations and utilities/services for Plugins
that allow consistent list, filter and detail views.

## QueryExtbase Route Enhancer

QueryExtbase Route Enhancer replaces extbase plugin query parameters with custom names and omits action and controller parameters.

### limitToPages
Required for QueryExtbase route enhancer to work, because without limit all routes would match.

### defaults
Behave the same as described [here](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Routing/AdvancedRoutingConfiguration.html#enhancers).

### namespace, extension, plugin, \_controller
Behave the same as described [here](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Routing/AdvancedRoutingConfiguration.html#extbase-plugin-enhancer).

### \_arguments
Replace the default query parameter name with a custom one. Key is the name, value the old. For Example `tag: overwriteDemand/tags`.

### aspects
Aspects with the suffix `Label` should use `LocaleModifier` to replace the query parameter name with localized names. So if a query parameter with the argument `page: currentPage` and an Aspect with the key `pageLabel` exists, the localized names would be used.

### types
Limit the route enhancer to certain page types, for example to enhance solr search result routes but not autocomplete routes. Defaults to `[0]`.

### example for News Extension

```
  News:
    limitToPages: [20]
    type: QueryExtbase
    extension: News
    plugin: Pi1
    _controller: 'News::list'
    defaults:
      page: '1'
    _arguments:
      page: currentPage
      category: overwriteDemand/categories
    aspects:
      page:
        type: StaticRangeMapper
        start: '1'
        end: '5'
      pageLabel:
        type: LocaleModifier
        default: page
        localeMap:
          -
            locale: 'de_DE.*'
            value: seite
      category:
        type: PersistedAliasMapper
        tableName: sys_category
        routeFieldName: slug
      categoryLabel:
        type: LocaleModifier
        default: category
        localeMap:
          -
            locale: 'de_DE.*'
            value: kategorie

```
