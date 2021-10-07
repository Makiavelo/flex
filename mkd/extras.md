## Validation
We don't offer any validation out of the box, but Flex allows you to do whatever you want when it's validation time.
Using a mix of 'Event Hooks' and metadata, you can achieve any complex validation you need.
We recommend searching for any good rule validation library out there, and here is an example of how it can be used within Flex.

Example model:
```php
class User extends Flex
{
    public $id;
    public $name;
    public $last_name;
    
    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
    }

    public function valid()
    {
        // If this method returns true, the record will be saved.

        if ($this->meta()->get('scope') === 'new') {
            // Add your validations for a new record
            // Example:
            if  (!$this->getLastName()) {
                $this->meta()->add('errors', ['Last name required']);
                return false;
            }
        } else {
            // Add your validations for any other state
        }
        
        return true;
    }

    public function preSave()
    {
        return $this->valid();
    }
```
We don't enforce any convention in the meta data object, so everything defined in this example can be done any way you want.
There's no convention for the meta 'errors' nor 'scope', that was just invented for this particular example. You can do whatever you want, we just provide the hook for it. Hook methods should return true or false, that's the only requirement.

The same can be done for the 'preDelete' hook.

postSave and postDelete don't have any impact in the flow of the database manipulation, but you can do whatever you want inside those methods, we only assure that they are executed right after their corresponding action. Same concept here, it's open to anything.

The counterpart of that example will be something like this:
```php
$repo = FlexRepository::get();

$user = new User();
$user->setName('John');
$user->meta()->add('scope', 'new');

$status = $repo->save($user); // This should be false because the scope is 'new' and there's no last name

$user->setLastName('Doe');
$status = $repo->save($user); // This should be true

$otherUser = new User();
$otherUser->setName('John');
$status = $repo->save($otherUser); // This should be true, no scope defined.

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
FlexRepository::freeze();
```

So in a real world example it will look like this:

```php
include('../vendor/autoload.php');

FlexRepository::freeze();
$repo = FlexRepository::get();
$status = $repo->connect([
    'host' => 'localhost',
    'db' => 'flex_test',
    'user' => 'myuser',
    'pass' => 'mypass'
]);
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