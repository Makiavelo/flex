# Flex
Flexible and minimalistic ORM tool for fast prototyping and development of PHP/MySQL applications.
The main approach is to have flexible models without worrying about database schemas, after that initial phase, the database can be freezed and handled manually.

## Requirements
PHP, MySQL and a table to work on. No table creation required nor model generation required.

## Install with composer
```
composer require makiavelo/flex
```
Or update dependencies in composer.json
```json
"require": {
    "makiavelo/flex": "dev-master"
}
```

## Install with single file
The repository contains a phar file which can be included directly to avoid using composer.
The phar can be found here: `/phar/flex.phar`
```php
include('flex.phar');

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;
```

## Quick tour
Here's a quick example of an implementation, let's say we have a json to dump into a db:
```php
$repo = FlexRepository::get();
$repo->connect([
    'host' => 'localhost',
    'db' => 'flex_test',
    'user' => 'myuser',
    'pass' => 'mypass'
]);

$json = '[{"name":"John", "last_name": "Doe"},...]'; // Dots to represent lots of other users

$data = json_decode($json, true);

foreach ($data as $userData) {
    $model = new Flex();
    $model->meta()->add('table', 'user');
    $model->name = $userData['name'];
    $model->last_name = $userData['last_name'];
    $repo->save($model);
}

$repo->saveCollection($models);

// Or shorter
foreach ($data as $userData) {
    $repo->create('user');
    $model->hydrate($userData);
    $repo->save($model);
}

// More efficient?
// Multiple inserts in a single query
$models = [];
foreach ($data as $userData) {
    $repo->create('user');
    $model->hydrate($userData);
    $models[] = $model;
}
$repo->saveCollection($models);
```
In this case, only the database is required, the 'user' table will be created automatically and store all the data imported from the JSON.

## Full documentation
Full documentation here: https://makiavleoflex.readthedocs.io

Switched to 'readthedocs' as main documentation handler

## Classes documentation
The documentation of the classes can be found in the 'phpdocs' folder. Can be viewed online on github pages here: https://makiavelo.github.io/flex/

## Testing
If you want to play with the code, install dev dependencies and run any of these commands:

```
./vendor/bin/phpunit tests --coverage-html tests/coverage
./vendor/bin/phpunit tests/unit
./vendor/bin/phpunit tests/functional
```
