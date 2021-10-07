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

### Relation Collections
The relations of type 'Has' and 'HasAndBelongs' add collections to the model. We added some magic methods to handle collections and make
life easier.

Examples:
```php
$user1 = new User();
$user1->setName('John');
$user1->setLastName('Doe');

$user2 = new User();
$user2->setName('Jack');
$user2->setLastName('Daniels');

$user3 = new User();
$user3->setName('Will');
$user3->setLastName('Ferrel');

$company = new Company();
$company->setName('Test Company');

// This could be done with $company->setUsers()
// but just showcasing...
$company->users()->add($user1);
$company->users()->add($user2);
$company->users()->add($user3);

// Returns user1
$company->users()->with(['name' => 'John'])->fetch();

// Returns user1 and user3
$company->users()->not()->with(['last_name' => 'Daniels'])->fetch();

// Removes user1 and user3
$company->users()->not()->with(['last_name' => 'Daniels'])->remove();

// Returns false, we deleted them
$company->users()->not()->with(['last_name' => 'Daniels'])->exists();

// Alias of the previous with but with a callable
$company->users()->with(function ($model) {
    if ($model->getLastName() === 'Daniels') {
        return true;
    } else {
        return false;
    }
})->fetch();

// Comparing against a model (only id is compared)
$someUser = new User();
$company->users()->with($someUser)->fetch();

// Add them again
$company->users()->add($user1);
$company->users()->add($user3);

// Empty all
$company->users()->clear();

// At any point you can save the model
FlexRepository::get()->save($company);
```
It's important to note that this only filters the already loaded models, and doesn't query the database to filter the collection.

The methods available for chaining are:

* __with:__ This is a filter method, and all the methods chained after this will use this filter.
  * This function accepts 3 types of conditions:
    * __Array:__ Will match against each $field->$value pair
    * __Callable:__ Can be a closure or any type of Callable. Matches if it returns true.
    * __Flex:__ Will match against the Flex model id only. 
* __not:__ This will negate the condition set in the 'with' condition (doing the opposite).
* __fetch:__ Fetch the collection, if no 'with' method was chained, will return everything.
* __remove:__ Will remove items from the collection based on the 'with' condition. If no 'with' was chained, will remove them all.
* __add:__ Add a new item to the collection.
* __clear:__ Will empty the collection.
* __exists:__ Will check that the 'with' condition has results. Is a shorthand method for 'fetch' that only returns a boolean if the condition has elements.
