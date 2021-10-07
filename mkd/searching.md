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