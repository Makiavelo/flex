# Flex
Flexible and minimalistic ORM tool for fast prototyping and development of PHP/MySQL applications.
The main approach is to have flexible models without worrying about database schemas (like NoSQL), after that initial phase, the database can be freezed and handled manually (usual MySQL).

What we offer:
* Micro ORM
* Relationships (Belongs, Has, HasAndBelongs)
* Traits (Reusable behaviors)
* Query hydrator
* Handling nested relationships
* Transactions
* Batch operations

## Full documentation
Full documentation, examples and tutorials here: https://makiavleoflex.readthedocs.io

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

### Need a quick script? we got you
Here's a quick example of an implementation. No dependencies, no config, just start.
You only need an empty database and access to it.

let's say we have a json to dump into a db:
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

### Need a strong model? we got you
So you liked it and want a full blown model, we got your back, any class can extend 'Flex' and use all of the available features.
Let's say we have a blog, and want to handle posts in different languages and tag them.

The code:
```php
$blogPost = new BlogPost();

$blogPost->setTitle('Blog post title');
$blogPost->setDescription('A long body');
$blogPost->setShortDescription('Short description');

$blogPost->translations()->add(
    $blogPost->translation('es', [
        'title' => 'Titulo de post',
        'description' => 'Un cuerpo largo en espanol',
        'short_description' => 'Descripcion corta'
    ])
);

$blogPost->setTags([
    Tag::build(['name' => 'News'],
    Tag::build(['name' => 'Programming'],
    Tag::build(['name' => 'Jobs']
]);

FlexRepository::get()->save($blogPost);
```
In this example we are using:
* Custom defined model
* Traits (Translatable)
* Relations ('Has' tags relation)

This model:
```php
use Makiavelo\Flex\Traits\Translatable;

class BlogPost extends Flex
{
    use Translatable;

    public $id;
    public $title;
    public $description;
    public $short_description;

    public function __construct()
    {
        parent::__construct();

        // Define the table for this model
        $this->meta()->add('table', 'post');

        // Define the 'Tags' relation
        $this->relations()->add([
            'name' => 'Tags',       // The name of the relation
            'table' => 'tag',       // The table of the relation
            'class' => 'Tag',       // The class of the related model
            'key' => 'blog_post_id', // The foreign key in the related table
            'type' => 'Has',        // The type of the relation
        ]);

        // Adding the trait/behavior
        $this->_translatableInit();
    }
}
```

### Need to search stuff? we got you
Using flex you can use the database any way you want. You will have access directly to PDO if needed.
Let's write a complex query with relations (based on previous example), and see how we handle them:

Example:
```php
$repo = FlexRepository::get();

// Fetch a blog post with relations joined
$query = "SELECT * FROM blog_post
            JOIN blog_post_translation ON blog_post_translation.blog_post_id = blog_post.id
            JOIN tag ON tag.blog_post_id = blog_post.id
          WHERE blog_post.id = :id";

// Add query parameters
$params = [':id' => $id];

// Add options for hydrating the results
$options = ['table' => 'blog_post', 'class' => 'BlogPost'];

$blogPosts = $repo->query($query, $params, $options);
$blogPost = $blogPosts[0];

echo $blogPost->getTitle();
echo $blogPost->getDescription();
echo $blogPost->getShortDescription();

$blogPost->getTranslations(); // Get all the translations
$blogPost->translations()->with('locale' = 'es')->fetch(); // Get spanish translations
$blogPost->tags()->not()->with('name' => 'News')->exist(); // Are there tags other than 'News' ?
$blogPost->tags()->with(['name' => 'Programming'])->remove(); // Remove programming tags
$blogPost->tags()->clear(); // Remove all tags
$blogPost->translations()->not()->with('locale' => 'es')->remove(); // Remove non-spanish translations

$repo->save($blogPost);
```

As you can see, the query is whatever you need it to be. No complex builders or headaches. Flex will automatically hydrate all the records, you only need to provide the main Class and table, all the relations will be extracted recursively from there.
You can turn off hydration (options array) or use PDO directly if only an array is needed.
__Models support lazy loading__, so if the relation tables weren't joined in the original query, Flex will try to fetch them when you try to use them.

## Classes documentation
The documentation of the classes can be found in the 'phpdocs' folder. Can be viewed online on github pages here: https://makiavelo.github.io/flex/
