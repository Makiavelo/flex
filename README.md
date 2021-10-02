# Flex
Flexible and minimalistic ORM tool for fast prototyping and development of PHP/MySQL applications.
The framework consists only of two Core files and a helpers file.
The main approach is to have flexible models without worrying about database schemas, after that initial phase, the database can be freezed and handled manually.

- [Table of contents](#flex)
  * [Requirements](#requirements)
  * [Install with composer](#install-with-composer)
  * [Install with single file](#install-with-single-file)
  * [Examples](#examples)
    + [Connecting to the database](#connecting-to-the-database)
    + [Creating models](#creating-models)
    + [Custom classes](#custom-classes)
    + [Custom field types](#custom-field-types)
    + [Internal fields](#internal-fields)
    + [Collections](#collections)
  * [Relations](#relations)
    + [Belongs](#belongs)
    + [Has](#has)
    + [HasAndBelongs](#hasandbelongs)
    + [HasAndBelongs with custom relation data](#hasandbelongs-with-custom-relation-data)
    + [Self referencing](#self-referencing)
  * [Searching for models](#searching-for-models)
  * [Complex searches](#complex-searches)
  * [Using the raw database connection](#using-the-raw-database-connection)
  * [Event hooks](#event-hooks)
  * [Transactionality](#transactionality)
  * [Freezing the database](#freezing-the-database)
  * [Documentation](#documentation)
  * [Testing](#testing)



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
$repo->connect('172.17.0.1', 'db', 'user', 'pass');

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
$model->meta()->add('table', 'user');
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
$model->getRelation() // get{CamelCasedRelationName}
$model->setRelation() // set{CamelCasedRelationName}
$model->isNew(); // if it was just created or was already saved on db
$model->isEmpty(); // if it has nothing on it
$model->meta(); // Get the metadata. Metadata can be anything, like a parameter bag.
$model->meta()->add(); // Add a metadata item
$model->meta()->get($path, $default); // Get a metadata item based on a path ($path = 'some->internal->attr')
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
        $this->meta()->add('table', 'user');
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
        $this->meta()->add('table', 'user');
        $this->meta()->add('fields', [
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
the attribute 'id' and all the attributes that start with a '_' (underscore) are considered as internal
and will never be persisted to the database, while 'id' is always generated as a primary key with autoincrement.

### Collections
FlexRepository can handle collections for all the operations (insert, update, delete).
Model relationships are not considered for insert statements.

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

## Relations
To use relations, the use of a custom class is required. We can define all the relations in the constructor of the model.

### Belongs
This is when we have a foreign key in our model, example:
```php
class User extends Flex {
    public $id;
    public $company_id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
        $this->relations()->add([
            'name' => 'Company',   // Name of the relation, indexing purposes
            'table' => 'company',  // Name of the table of the relation
            'class' => 'Company',  // Class of the relation
            'key' => 'company_id', // They key in this model that points to the parent
            'type' => 'Belongs',   // The type of relation
        ]);
    }

    public function getCompanyName()
    {
        return $this->getCompany()->getName();
    }
}
```

Example implementation:
```php
$repo = FlexRepository::get();

$company = new Company();
$company->setName('test_company');

$user = new User();
$user->setCompany($company);
$user->setName('John');
$user->setLastName('Doe');

$repo->save($user);
```

### Has
This is the opposite of the 'Belongs' relation type. They usually go together on opposite sides.

Example (based on the previous one):
```php
$repo = FlexRepository::get();

$company = new Company();
$company->setName('test_company_2');

$user1 = new User();
$user1->setName('Jack');
$user1->setLastName('Daniels');

$user2 = new User();
$user2->setName('John');
$user2->setLastName('Doe');

$company->setUsers([$user1, $user2]);

$repo->save($company);
```

The model should be defined like this:
```php
class Company extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'company');
        $this->relations()->add([
            'name' => 'Users',     // The name of the relation
            'table' => 'user',     // The table of the relation
            'class' => 'User',     // The class of the related model
            'key' => 'company_id', // The foreign key in the related table
            'type' => 'Has',       // The type of the relation
        ]);
    }
}
```

### HasAndBelongs
This is the 'Many-to-Many' case. Let's check an example:
```php
$repo = FlexRepository::get();

$user = new User();
$user->setName('John');

$tag1 = new Tag();
$tag1->setName('Tag 1');

$tag2 = new Tag();
$tag2->setName('Tag 2');

$user->setTags([$tag1, $tag2]);

$status = $repo->save($user);

// Now both objects have the relation
$user->getTags();
$tag1->getUsers();
```

Models configuration:
```php
class User extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
        $this->relations()->add([
            'name' => 'Tags',               // Name of the relation
            'table' => 'tag',               // Name of the related table
            'relation_table' => 'user_tag', // Name of the relation table (automatically created)
            'class' => 'Tag',               // Class of the related table
            'key' => 'user_id',             // Key of this model in the relation table
            'external_key' => 'tag_id',     // Key of the related model in the relation table
            'type' => 'HasAndBelongs',      // Type of the relation
        ]);
    }
}

class Tag extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'tag');
        $this->relations()->add([
            'name' => 'Users',               // Name of the relation
            'table' => 'user',               // Name of the related table
            'relation_table' => 'userb_tag', // Name of the relation table (automatically created)
            'table_alias' => '',             // Class of the related table
            'class' => 'User',               // Key of this model in the relation table
            'key' => 'tag_id',               // Key of the related model in the relation table
            'external_key' => 'user_id',     // Key of the related model in the relation table
            'type' => 'HasAndBelongs',       // Type of the relation
        ]);
    }
}
```

### HasAndBelongs with custom relation data
Usually, 'HasAndBelongs' doesn't make much sense, except for specific stuff. The ideal form of the relation would be
something like: `'Has'<- 'Belongs' -> 'Has'`
That means a relation that has something, which in turn belongs the other thing, while keeping information
of the relationship in the 'middle-man', like creation date, status, etc.

Example:
```php
$user1 = new User();
$user1->setName('John');

$user2 = new User();
$user2->setName('Jack');

$tag1 = new Tag();
$tag1->setName('Tag 1');

$tag2 = new Tag();
$tag2->setName('Tag 1');

$userTag = new UserTag();
$userTag->setUsers([$user1, $user2]);
$userTag->setTags([$tag1, $tag2]);
$userTag->setDate(date("Y-m-d"));
$userTag->setStatus('active');

// Save the relation table with all the relation data
FlexRepository::get()->save($userTag);
```

Models config:
```php
class User extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
        $this->relations()->add([
            'name' => 'UserTags',
            'table' => 'user_tag',
            'table_alias' => '',
            'class' => 'UserTag',
            'key' => 'user_id',
            'type' => 'Has',
        ]);
    }
}

class Tag extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'tag');
        $this->relations()->add([
            'name' => 'UserTags',
            'table' => 'usera_tag',
            'table_alias' => '',
            'class' => 'UserTag',
            'key' => 'tag_id',
            'type' => 'Has',
        ]);
    }
}

class UserTag extends Flex {
    public $id;
    public $user_id;
    public $tag_id;
    public $date;
    public $status;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'usera_tag');
        $this->relations()->add([
            'name' => 'User',
            'table' => 'user',
            'table_alias' => '',
            'class' => 'User',
            'key' => 'user_id',
            'type' => 'Belongs',
        ]);

        $this->relations()->add([
            'name' => 'Tag',
            'table' => 'tag',
            'table_alias' => '',
            'class' => 'Tag',
            'key' => 'tag_id',
            'type' => 'Belongs',
        ]);
    }
}
```
As you can see, we now have two models with a 'Has' relation to 'UserTag', which in turn, has to 'Belong' relations to them.
Now we can store any aditional information in the intermediate model to keep track of extra stuff.

### Self referencing
It's a common practice to have self references in tables, like a user who has a boss, which is also a user.

Example:
```php
$user = new User();
$user->setName('John');
$user->setLastName('Doe');

$parent = new User();
$parent->setName('Jack');
$parent->setLastName('Daniels');

$user->setParent($parent);

$result = FlexRepository::get()->save($user);
```

Model config:
```php
class User extends Flex {
    public $id;
    public $parent_id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
        $this->relations()->add([
            'name' => 'Parent',
            'table' => 'user',
            'table_alias' => 'parent', // **THIS is important**
            'class' => 'User',
            'key' => 'parent_id',
            'type' => 'Belongs',
        ]);
    }
}
```
As you can see, it's a normal 'Belongs' relation, but there's one important thing to notice here, and it's the
`'table_alias'` parameter defined in the relation.
Without this alias, Flex has no way to know who is who when it gets the raw results from the database.

## Searching for models
There are a couple of convenience methods to search for models in the database:
```php
FlexRepository::get()->find($table, $condition, $params, $options);
FlexRepository::get()->findOne($table, $condition, $params, $options);
```
Note: this doesn't support relations/joins, just a simple getter for single table conditions.

Example:
```php
$repo = FlexRepository::get();
$params = [':name' => 'John', ':like' => '%php developer%'];
$options = ['class' => 'Some\Class'];
$repo->find("user", "name = :name AND description LIKE :like", $params, $options);
```

This will return an array of 'Some\Class' models, if no option is provided, then it will just create Flex models.
if the 'hydrate' option is sent as false, then it will just return de array of results.

We leave the PDO object open to do whatever.
Since we only support MySQL, we don't need to translate complex querys to multiple SQL languages.

## Complex searches
To search for anything, with any complexity on the query, we have the 'query' method. The only thing to keep in mind while using this method
is the name of the fields we are retrieving. The auto-hydration is 'kinda' smart, but needs the correct input to hydrate.
If no hydration is needed, or it's going to be done manually later, then you can do whatever.

Examples:
```php
$repo = FlexRepository::get();

// Simple query
$result = $repo->query('SELECT * FROM test_table');

// Query with params
$result = $repo->query('SELECT * FROM test_table WHERE name = :name', [':name' => 'TestQuery']);

// Custom classes and hydration
// Search for a user with a joined company, this will return an hydrated User object with the
// corresponding hydrated Company object as a relation. (Check relations for more details)
$result = $repo->query(
    "SELECT * FROM user JOIN company ON company.id = user.company_id WHERE company.id = ':company_id'",
    [':company_id' => $someId],
    ['class' => 'User']
);

// Custom classes and hydration with complex relations
$result = $repo->query(
    "SELECT * FROM user JOIN user_tag ON user_tag.user_id = user.id JOIN tag ON user_tag.tag_id = tag.id",
    [], // No params needed
    ['class' => 'User']
);
// This will return a User model with the Tag models as a collection (depending on relation configuration)
```

## Using the raw database connection
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
        $this->meta()->add('table', 'law_firm');
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
There is an exception to this, while the database is not frozen, no transactionality will be available due to limitations of the
MySQL engine, but as soon as the database is frozen, everything will be transactional.

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

## Documentation
The documentation of the classes can be found in the 'docs' folder. Can be viewed online on github pages here: https://makiavelo.github.io/flex/

## Testing
If you want to play with the code, install dev dependencies and run any of these commands:

```
./vendor/bin/phpunit tests --coverage-html tests/coverage
./vendor/bin/phpunit tests/unit
./vendor/bin/phpunit tests/functional
```
