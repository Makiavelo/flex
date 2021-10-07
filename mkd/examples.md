## Examples

### Connecting to the database
FlexRepository is the class in charge of database manipulations. It's a singleton, so it should connect once at least in the code.
```php
include('../vendor/autoload.php');


$repo = FlexRepository::get();
$status = $repo->connect([
    'host' => 'localhost',
    'db' => 'flex_test',
    'user' => 'myuser',
    'pass' => 'mypass'
]);
```

### Using other databases
Flex is built for MySQL, and doesn't aim to be database agnostic. Anyways, you still can use the database of your choice, but it will require you to build a driver for it.

All the queries and their logic are into the driver, so to make it work with any other database it's required to copy this file:

`src/Drivers/PDOMySQL.php` 

Understand what is happenning there and rewrite the queries for the database required.

Example:
```php
public function delete($table, Flex $model)
{
    $query = "DELETE FROM {$table} WHERE id = ?"; // <--- CHANGE THIS
    $stmt = $this->db->prepare($query);           // <--- and of course your connector to whatever
    $result = $stmt->execute([$model->id]);
    return $result;
}
```

Then just replace the driver in `FlexRepository`
```php
$driver = new MySQLiDriver(....);
FlexRepository::get()->useDriver($driver);

// OR
FlexRepository::get(['driver' => $driver]); // In the first call only

// MySQLiDriver should have the same methods as PDOMySQL.php
// and connect to the real connector with mysqli_connect(...)
// to execute the actual queries in the db engine
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

### Batch operations with models
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