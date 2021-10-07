## Traits
The idea behind this is to re-use common logic between models. There's not much magic behind this, it's just traits.

The ones included in Flex are:

* __Timestampable:__ Adds created and update date to models.
* __Sluggable:__ Adds a slug field, which is created based on a mix of other fields.
* __Versionable:__ Adds versioning to a model, so the model using this behavior stores a copy of itself along with a version number. A model can switch to any version with a single command.
* __Geopositioned:__ Adds latitude and longitude to a model, and also an utility distance calculator function.
* __Translatable:__ Adds translation records for the selected fields.

The source can be checked in `src/Traits`.

There is no magic behind these traits, you have to add the required method calls in the expected hooks to make them work.

### Timestampable
Add it to a model
```php
use Makiavelo\Flex\Traits\Timestampable;

class User extends Flex
{
    use Timestampable;

    public $id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
    }

    public function preSave()
    {
        // The trait hook is directly called, no magic.
        $this->_timestampablePreSave();
        return true;
    }
}
```
Usage
```php
$repo = FlexRepository::get();

$model = new User();
$model->setName('John');
$model->setLastName('Doe');
$repo->save($model);

echo $model->getCreatedAt();
echo $model->getUpdatedAt();
```

### Sluggable
```php
use Makiavelo\Flex\Traits\Sluggable;

class User extends Flex
{
    use Sluggable;

    public $id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');

        // Calling in the constructor the required method
        $this->_sluggableInit([
            'fields' => ['name', 'last_name'],
            'update' => true
        ]);
    }

    public function preSave()
    {
        // The trait hook is directly called, no magic.
        $this->_sluggablePreSave();
        return true;
    }
}
```
Usage:
```php
$repo = FlexRepository::get();

$model = new User();
$model->setName('John');
$model->setLastName('Doe');
$repo->save($model);

echo $model->getSlug(); // john-doe
```
### Versionable
```php
use Makiavelo\Flex\Traits\Versionable;

class User extends Flex
{
    use Versionable;

    public $id;
    public $name;
    public $last_name;
    public $description;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
    }

    public function preSave()
    {
        // The trait hook is directly called, no magic.
        $this->_versionablePreSave();
        return true;
    }
}
```
Usage:
```php
$repo = FlexRepository::get();

$model = new User();
$model->setName('John');
$model->setLastName('Doe');
$model->setDescription('Some description');
$repo->save($model); // Version 1 created

$model->setName('Jack');
$repo->save($model); // Version 2 created

$model->changeVersion(1);
$repo->save($model); // Version 3 created, equal to version 1

echo $model->getVersion(); // 3
```

### Geopositioned
This one only adds fields and functionality, so no hooks required to make it work.
```php
use Makiavelo\Flex\Traits\Geopositioned;

class User extends Flex
{
    use Geopositioned;

    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
    }
}
```
Usage:
```php
$repo = FlexRepository::get();

$model = new User();
$model->setName('John');

// Florence
$model->setLat('42.8201432');
$model->setLng('10.7506802');

// Rome
$distance = $model->distanceTo('41.9097306', '12.2558141');
echo $distance; // Distance in meters
echo round($distance/1000); // Distance in kilometers
```

### Translatable
Add trait to a model:
```php
class Post extends Flex
{
    use Translatable;

    public $id;
    public $title;
    public $body;
    public $description;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'post');

        // Calling in the constructor the required method
        $this->_translatableInit();
    }
}
```

Usage:
```php
$model = new Post();
$model->setTitle('A post title');
$model->setBody('Some long body in default lang');
$model->setDescription('Some cool description');

$model->translations()->add(
    $model->translation('es', [
        'title' => 'Titulo de post',
        'description' => 'Un cuerpo largo en espanol',
        'short_description' => 'Descripcion corta'
    ])
);

$model->translations()->add(
    $model->translation('fr', [
        'description' => 'I have to learn french...',
        'short_description' => 'Maybe...'
    ])
);

FlexRepository::get()->save($model);
```
The behavior will add a relation to the model called 'Translations' and add a table called 'post_translation' ({table}_translation).
To handle the translations table, a simple Flex object is used, so you can treat the translations as another 'Has' relation.
A convenience method is added to the model called 'translation' which basically creates that flex model and adds it to the translations list.