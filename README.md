# Flex
Flexible and minimalistic ORM tool for fast prototyping and development of PHP/MySQL applications.
The framework consists only of two Core files and a helpers file.
The main approach is to have flexible models without worrying about database schemas, after that initial phase, the database can be freezed and handled manually.

## Requirements
PHP, MySQL and a table to work on. No table creation required nor model generation required.

## Install (with composer)
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

## Examples

### Connecting to the database
FlexRepository is the class in charge of database manipulations. It's a singleton, so it should connect once at least in the code.
```php
include('../vendor/autoload.php');


$repo = FlexRepository::get();
$status = $repo->connect(
    '172.17.0.1',
    'flex_test',
    'root',
    'root'
);
```

### Creating models
Flex models are just objects with a couple of methods to facilitate it's handling. Flex models don't comunicate with the database, they just store data and logic.

```php
$model = new Flex();
$model->addMeta('table', 'user');
$model->name = 'John';
$model->last_name = 'Doe';

FlexRepository::get()->save($model);
```

At this point the model is saved to the database. How? well, FlexRepository will look for the table defined in the metadata, and if it's not found it will create it. The same happens with all the fields, if they are not found, they are created. After that all the models with the same structure will no longer modify the table unless they add new fields. Field types can be updated but that will be covered in the next examples.

An alternative way to create models is via FlexRepository:

```php
$repo = FlexRepository::get();
$model = $repo->create('user'); // This avoids the need of the metadata setter
$model->name = 'John';
$model->last_name = 'Doe';

$repo->save($model);
echo $model->getId(); // a save returns an id
```

After a Flex model is created, there are a couple of utility magic methods available
```php
$model->getName(); // get{CamelCasedAttribute}()
$model->setName($value); // set{CamelCasedAttribute}()
$model->isNew(); // if it was just created or was already saved on db
$model->_meta(); // Get the metadata. Metadata can be anything, like a parameter bag.
$model->addMeta(); // Add a metadata item
$model->getMeta($path, $default); // Get a metadata item based on a path ($path = 'some->internal->attr')
$className::build($data); // Create an object of target class from array or object
                          // Example: Flex::build($data) or User::build($data)
```

### Custom classes
Another way to create models is to use custom classes. This opens up a lot of possibilities.

Example:

```php
use Makiavelo\Flex\Flex;

class User extends Flex
{
    public $id;
    public $name;
    public $description;

    public function __construct()
    {
        $this->addMeta('table', 'user');
        parent::__construct();
    }

    // Custom logic here
    public function getNameAndDesc()
    {
        return $this->name . ' - ' . $this->description;
    }
}
```

A custom class can be saved exactly the same way as a raw Flex model.

```php
$user = new User();
$user->setName('John');
$user->setCode('1234');
$user->setDescription('Some desc...');

FlexRepository::get()->save($user);
```

When all the attributes were defined in a class, we get access to getters and setters instantly. In a raw flex model, it's required
to first set the attribtues manually.

### Custom field types
It is possible to define MySQL field types via Flex metadata attributes.

Example:
```php
use Makiavelo\Flex\Flex;

class User extends Flex
{
    public $id;
    public $name;
    public $description;
    public $code;

    public function __construct()
    {
        $this->addMeta('table', 'user');
        $this->addMeta('fields', [
            'name' => ['type' => 'VARCHAR(150)', 'nullable' => true],
            'description' => ['type' => 'TEXT', 'nullable' => true],
        ]);
        parent::__construct();
    }
}
```

When FlexRepository is saving or updating a model, if it finds a difference between field types, it will apply the ones defined in the model.
If the field wasn't created before, it will apply the metadata type directly.
The default type for all the fields is 'TEXT' if not defined via metadata.

### Internal fields
There are two fields that are considered as internal. 'id' and '_meta'.
'_meta' will never be persisted to the database, while 'id' is alwas generated as a primary key with autoincrement.

### Collections
FlexRepository can handle collections for all the operations (insert, update, delete).

Example:
```php
$repo = FlexRepository::get();

$model1 = $user; // Some already loaded model

$model2 = $repo->create('user');
$model2->name = 'Jack';
$model2->last_name = 'Daniels';

$model3 = $repo->create('user');
$model3->name = 'Will';
$model3->last_name = 'Ferrel';

$repo->saveCollection([$model1, $model2, $model3]);
```
That will save the whole collection to the database. There's a catch, all the models in the list should have the same structure.
It doesn't matter if the objects are new or not, FlexRepository will insert/update where needed.
All the models should have the same structure, since we are using the first element as our table update reference.
The idea of this collection method is to have a performance boost while dealing with lots of records, that's why only the first model
is used for schema syncronization.
All the inserts will be performed in a single query, while updates will be handled individually.

If you know models will have different attributes, then the approach would be to iterate all the models and save them individually.

Deleting collections:
```php
$repo = FlexRepository::get();
$repo->deleteCollection([...]);
```

## Searching for models
There's only one convenience method created for searching models on development stages. It doesn't support joins or anything fancy, that's up to the developer. Notice that it's open for SQL injection, so using this on prod is a no-go.
```php
FlexRepository::get()->find($table, $condition);
```

Example:
```php
$repo = FlexRepository::get();
$repo->find("user", "name = 'John' AND description LIKE '%php developer%'");
```

This will return an associative array with all the results found.

The idea of the ORM is not to be a wildcard to search for anything, like big query builders. We leave the PDO object open to do whatever.
Since we only support MySQL, we don't need to translate complex querys to multiple SQL languages.

## Using the database connection
The 'FlexRepository' instance will have a 'db' attribute attached to it. That's the instance of the PDO connection, so the developer can execute
any query to fetch the data.

Example:
```php
$db = FlexRepository::get()->db;

// Prepare the query, then send the parameters on execution
$query = "SELECT * FROM user WHERE name = ?";
$stmt = $db->prepare($query);
$result = $stmt->execute(['John']);
```

### Hydrating database results
After a query is executed, it's probable we want to load models from it. There's a convenience method defined on Flex models called 'hydrate' which will convert any associative array or object and set it's attributes from it.

Example:
```php
$result = ['name' => 'John', 'description' => 'A PHP Developer'];
$model = new User();
$model->hydrate($result);

$model->getName(); // 'John'
$model->getDescription(); // 'A PHP Developer'
```

Simpler syntax:
```php
$result = ['name' => 'John', 'description' => 'A PHP Developer'];
$model = User::build($result);

$model->getName(); // 'John'
$model->getDescription(); // 'A PHP Developer'
```


DB Example
```php
$db = FlexRepository::get()->db;

// Prepare the query, then send the parameters on execution
$query = "SELECT * FROM user";
$result = $db->query($query)->fetchAll();
$users = [];

foreach ($result as $item) {
    $users[] = User::build($item);
}

// Or something smaller...
$users = array_map(['User', 'build']);
```

Flex models example:
```php
$data = [
    ['name' => 'John'],
    ['name' => 'Jack'],
    ['name' => 'Will']
];
$models = array_map('Makiavelo\\Flex\\Flex', $data);

foreach ($models as $model) {
    echo $model->getName();
}
```

## Event hooks
All the models have event hooks defined, but they won't do anything unless overriden. This is an example class that adds events functionality.

```php
class User extends Flex
{
    public $id;
    public $name;
    public $description;

    public function __construct()
    {
        $this->addMeta('table', 'law_firm');
        parent::__construct();
    }

    public function valid()
    {
        // Add your custom validation logic
        // this isn't a hook, just a convenience method.
        return true;
    }

    public function preSave()
    {
        // This is executed before saving anything related
        // to the model. Should return a boolean.
        // If this method returns false, the model won't be saved.
        return $this->valid();
    }

    public function postSave()
    {
        // This is executed after the model was saved to the database.
        // Can return anything, won't impact the result.
        return true;
    }

    public function preDelete()
    {
        // This is executed before deleting a model
        // Should return a boolean.
        // If this method returns false, the model won't be deleted.
        return true;
    }

    public function postDelete()
    {
        // This is executed after the model was deleted from the database.
        // Can return anything, won't impact the result.
        return true;
    }
}
```

## Transactionality
All the operations on the database are performed in a transactional way, all or nothing.
While saving collections, the schema updates and the inserts/updates will be contained in a transaction, so if any of the queries fail
then everything will be rollbacked.

## Freezing the database
The tables auto-update functionality (like a NoSQL database) is pretty comfortable on development/prototyping stages, but won't be secure enough
for production environments. The idea is to grab the rough version created by 'Flex' and update to have the proper types, indexes, etc.

To prevent the models from updating the database, a static method should be called:

```php
FlexRepository::$freeze = true;
```

So in a real world example it will look like this:

```php
include('../vendor/autoload.php');

FlexRepository::$freeze = true;
$repo = FlexRepository::get();
$status = $repo->connect(
    '172.17.0.1',
    'flex_test',
    'root',
    'root'
);
```

This will completely turn off the automatic schema synchronization, so saving models can fail if the attributes aren't defined on the database.

## Testing
If you want to play with the code, install dev dependencies and run any of these commands:

```
./vendor/bin/phpunit tests --coverage-html tests/coverage
./vendor/bin/phpunit tests/unit
./vendor/bin/phpunit tests/functional
```
