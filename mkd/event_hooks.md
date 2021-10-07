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