# Flex
Flexible and minimalistic ORM tool for fast prototyping and development of PHP/MySQL applications.
The framework consists only of two Core files and a helpers file.
The main approach is to have flexible models without worrying about database schemas, after that initial phase, the database can be freezed and handled manually.

- [Table of contents](#flex)
  * [Requirements](install.md#requirements)
  * [Install with composer](install.md#install-with-composer)
  * [Install with single file](install.md#install-with-single-file)
  * [Quick tour](quick_tour.md#quick-tour)
  * [Examples](examples.md#examples)
    + [Connecting to the database](examples.md#connecting-to-the-database)
    + [Using other databases](examples.md#using-other-databases)
    + [Creating models](examples.md#creating-models)
    + [Custom classes](examples.md#custom-classes)
    + [Custom field types](examples.md#custom-field-types)
    + [Internal fields](examples.md#internal-fields)
    + [Collections](examples.md#collections)
  * [Relations](relations.md#relations)
    + [Belongs](relations.md#belongs)
    + [Has](relations.md#has)
    + [HasAndBelongs](relations.md#hasandbelongs)
    + [HasAndBelongs with custom relation data](relations.md#hasandbelongs-with-custom-relation-data)
    + [Self referencing](relations.md#self-referencing)
    + [Relation Collections](relations.md#relation-collections)
  * [Traits](traits.md#traits)
    + [Timestablable](traits.md#timestampable)
    + [Sluggable](traits.md#sluggable)
    + [Versionable](traits.md#versionable)
    + [Geopositioned](traits.md#geopositioned)
    + [Translatable](traits.md#translatable)
  * [Searching for models](searching.md#searching-for-models)
  * [Complex searches](searching.md#complex-searches)
  * [Using the raw database connection](searching.md#using-the-raw-database-connection)
  * [Event hooks](event_hooks.md#event-hooks)
  * [Transactionality](extras.md#transactionality)
  * [Freezing the database](extras.md#freezing-the-database)
  * [Documentation](extras.md#documentation)
  * [Testing](extras.md#testing)