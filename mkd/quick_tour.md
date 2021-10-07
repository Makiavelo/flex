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