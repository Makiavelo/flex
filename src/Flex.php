<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\RelationManager;
use Makiavelo\Flex\Util\Common;
use Makiavelo\Flex\Meta;
use Makiavelo\Flex\Chainer;

/**
 * Flexible models
 * 
 * @todo Implement collections
 * @todo Add searcher in collection (like Common::find)
 */
class Flex
{
    /**
     * Internal variable for meta data like table name
     * @var Meta
     */
    public $_meta;

    /**
     * Model relations are stored here
     * @var Relation[]
     */
    public $_relations;

    /**
     * @var Chainer
     */
    public $_chainer;

    public function __construct()
    {
        $this->_meta = new Meta();
        $this->_relations = new RelationManager();
        $this->_chainer = new Chainer();
    }

    /**
     * Convert an array/object result to this class
     * this class can be Flex or any class that extended it
     * 
     * @param mixed $data
     * 
     * @return mixed
     */
    public static function build($data, $table = '')
    {
        $class = static::class;
        $model = new $class();
        $model->hydrate($data);

        if ($table) {
            $model->meta()->add('table', $table);
        }

        return $model;
    }

    /**
     * Populate the instance with the given data.
     * If an alias is provided, then it will try to hydrate
     * using that table alias instead.
     * 
     * Internally we are using the 'hydrateRelations' method
     * which starts the recursion loop to hydrate everything
     * available, including child objects.
     * 
     * Internally, $data can be a collection, this happens when
     * querying a 'Has' or 'HasAndBelongs' relation, which returns
     * a row for each relation match.
     * 
     * @param mixed $data The array/object to hydrate
     * @param string $alias Alias used
     * 
     * @return Flex
     */
    public function hydrate($data, $alias = '')
    {
        // Turn to array in case it's object
        $data = $this->toArray($data);
        
        // Get the first result if it's a collection, or
        // just de values if it's an associative.
        $currentData = Common::isCollection($data) ? $data[0] : $data;

        if (get_class($this) === 'Makiavelo\\Flex\\Flex') {
            $this->hydrateFlex($currentData);
        } else {
            $hydrated = $this->hydrateCustomClass($currentData, $alias);

            if ($hydrated) {
                // Force a collection format
                $collection = Common::isCollection($data) ? $data : [$data];

                // Remove own fields to prevent circular dependencies
                $modified = $this->removeTableFields($alias, $collection);

                // Only custom classes should have relations
                $this->hydrateRelations($modified);
            }
        }

        return $this;
    }

    /**
     * Helper method, turn input data to array
     * 
     * @param mixed $data
     * 
     * @return array
     */
    public function toArray($data)
    {
        if (!is_array($data)) {
            // Convert to associative array
            $data = json_decode(json_encode($data), true);
        }

        return $data;
    }

    /**
     * Remove all the fields related to an alias or table
     * form the $data collection.
     * The idea here is that as soon as an object was hydrated
     * it is removed from the data and perform a recursive hydration
     * without that information until there's nothing to hydrate.
     * 
     * @param mixed $alias
     * @param mixed $data
     * 
     * @return array
     */
    public function removeTableFields($alias, $data)
    {
        $aliasOrTable = $this->meta()->getAliasOrTable($alias);
        
        // Loop all the rows
        foreach ($data as $index => $row) {

            // Loop each field
            foreach ($row as $field => $value) {

                $fieldData = $this->fieldData($field);
                if ($fieldData['length'] === 2) {
                    // If the table name matches the current alias/table
                    // then remove it from the collection.
                    if ($fieldData['alias_or_table'] === $aliasOrTable) {
                        unset($data[$index][$field]);
                        if (!$data[$index]) {
                            // If a row is empty, remove it.
                            unset($data[$index]);
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Helper method to extract information about a database field
     * DB should return the following format: {table_name_or_alias}.{field_name}
     * 
     * Here we extract the table/alias name, and the field name
     * 
     * @param string $field
     * 
     * @return array
     */
    public function fieldData($field)
    {
        $data = [];
        $parts = explode('.', $field);
        $data['length'] = count($parts);

        if ($data['length'] === 2) {
            $data['alias_or_table'] = $parts[0];
            $data['name'] = $parts[1];
        } else {
            $data['alias_or_table'] = '';
            $data['name'] = $parts[0];
        }

        return $data;
    }

    /**
     * Hydrates this instance with the data from the
     * $data collection.
     * Only defined fields will be populated.
     * 
     * The counterpart of this class is 'hydrateFlex' which
     * does the opposite.
     * 
     * @param mixed $data
     * @param string $alias
     * 
     * @return void
     */
    public function hydrateCustomClass($data, $alias = '')
    {
        // If this is a custom class with attributes, then just stick
        // to the defined fields.
        $edited = false;
        $attrs = $this->getAttributes();
        if ($attrs) {
            $aliasOrTable = $this->meta()->getAliasOrTable($alias);
            $preffix = $aliasOrTable ? $aliasOrTable . '.' : '';
            $this->id = Common::get($data, $preffix . 'id');
            foreach ($attrs as $name => $value)
            {
                $found = Common::get($data, $preffix . $name);
                if ($found) {
                    $this->$name = $found;
                    $edited = true;
                }
            }
        }

        return $edited;
    }

    /**
     * Hydrate a Flex object.
     * This required a specific method since Flex objects have no
     * definition, so every field found in the $data collection will
     * be set as an attribute.
     * 
     * @param mixed $data
     * 
     * @return void
     */
    public function hydrateFlex($data)
    {
        // If this is an instance of Flex then it has no
        // Attribtues defined, let's take what we have from data
        foreach ($data as $attr => $value) {
            $fieldData = $this->fieldData($attr);
            $attrName = $fieldData['name'];
            $this->$attrName = $value;
        }
    }

    /**
     * Hydrate all the relations found in the instance.
     * Only the attributes present in the $data collection will
     * be used to hydrate childs, so no queries will be executed
     * to fetch relations from the database.
     * 
     * @param mixed $data
     * 
     * @return void
     */
    public function hydrateRelations($data)
    {
        if ($data) {
            $relations = $this->relations()->get();
            if ($relations) {
                foreach ($relations as $relation) {
                    if ($relation->type === 'Belongs') {
                        $this->hydrateBelongs($relation, $data);
                    } elseif ($relation->type === 'Has') {
                        if ($data) {
                            $this->hydrateHas($relation, $data);
                        }
                    } elseif ($relation->type == 'HasAndBelongs') {
                        if ($data) {
                            $this->hydrateHasAndBelongs($relation, $data);
                        }
                    }
                }
            }
        }
    }

    public function hydrateHasAndBelongs($relation, $data)
    {
        $this->hydrateHas($relation, $data);
    }

    /**
     * Hydrate a relationship of the type 'Has'
     * This method triggers hydration of child objects.
     * 
     * @param mixed $relation
     * @param mixed $data
     * 
     * @return void
     */
    public function hydrateHas($relation, $data)
    {
        // Force collection format, a has relationship 
        // expects an array of results.
        if (!Common::isCollection($data)) {
            $data = [$data];
        }

        // For each row, hydrate the instance of the relation class
        foreach ($data as $row) {
            $class = $relation->class;
            $model = new $class();
            $model->hydrate($row);

            if (!$model->isEmpty()) {
                $relation->add($model);
            }
        }

        $relation->loaded = true;
    }

    /**
     * Hydrate a relationship of the type 'Belong'
     * This method triggers hydration of child objects.
     * 
     * @param mixed $relation
     * @param mixed $data
     * 
     * @return void
     */
    public function hydrateBelongs($relation, $data)
    {
        $class = $relation->class;
        $related = new $class;
        $related->hydrate($data, $relation->tableAlias);
        $this->{$relation->key} = $related->id;
        if (!$related->isEmpty()) {
            $relation->setInstance($related);
        }

        $relation->loaded = true;
    }

    /**
     * Check if it's a new instance or a loaded one
     * 
     * @return boolean
     */
    public function isNew()
    {
        if (isset($this->id) && $this->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine if an object is empty
     * 
     * @return boolean
     */
    public function isEmpty()
    {
        $empty = true;
        $attrs = $this->getAttributes();
        if ($attrs) {
            foreach ($attrs as $name => $value) {
                if ($value) {
                    $empty = false;
                    break;
                }
            }
        }

        return $empty;
    }

    /**
     * Magic method to handle getters and setters
     * 
     * this method will first try to find an attribute from a getter
     * or setter for an attribute:
     * Example: get{CamelCaseAttr}()
     *          set{CamelCaseAttr}()
     * 
     * After that it will look into relationships
     * Example: get{RelationshipName}()
     *          set{RelationshipName}()
     * 
     * In the case of getters, if no instance is defined, it will try
     * to create one from the database.
     * 
     * After that it will look for relation names called as a function
     * to enable collection magic methods. This works for 'Has' and
     * 'HasAndBelongs' relations.
     * Format: {camelCaseRelationName}()
     * Examples:
     *      users()
     *      ownedCars()
     * 
     *          
     * 
     * All the relationships information is taken from
     * $this->_meta['relations']
     * 
     * @param mixed $name
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // Check if it's a getter/setter
        // first 3 chars are 'get' or 'set'
        $getSetPreffix = substr($name, 0, 3);
        if ($getSetPreffix === 'get' || $getSetPreffix === 'set') {

            // Get the attribute name in snake case and camel case
            $names = $this->_getPropertyNames($name);

            // If the property exists, get or set it
            // Properties have snake case to mimic db tables.
            if (property_exists($this, $names['attribute'])) {
                return $this->_propertyGetterSetter($getSetPreffix, $names['attribute'], $arguments);
                
            } elseif ($this->relations()->has($names['relation'])) {
                // Search for relationships
                $relation = $this->relations()->get($names['relation']);
                if ($getSetPreffix === 'get') {
                    return $relation->getInstance($this);
                } else {
                    $relation->setInstance($arguments[0]);
                    return $this;
                }
            } else {
                throw new \Exception("Undefined attribute '" . $names['attribute'] . "'");
            }
        } else {
            // It's not a getter, setter nor attribute name
            // so check for relations to return all the collection 
            // methods available: ['not', 'with', 'add', 'remove', 'clear', 'exists', 'fetch']
            if ($this->relations()->has($name, true)) {
                return $this->_chainer->addRelation($this, $name, $arguments);
            } elseif ($this->meta()->get('chain')) {
                return $this->_chainer->handleMethods($this, $name, $arguments);
            }
            throw new \Exception("Undefined method '" . $name . "'");
        }
    }

    /**
     * Internal method to get attribute and relation names based
     * on the original method call.
     * 
     * @param mixed $name
     * 
     * @return array
     */
    public function _getPropertyNames($name)
    {
        $camelCase = substr($name, 3);
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCase));
        return [
            'relation' => $camelCase,
            'attribute' => $snakeCase
        ];
    }

    /**
     * Internal method to set or set a property
     * 
     * @param mixed $getSetPreffix
     * @param mixed $snakeCaseAttr
     * @param array $arguments
     * 
     * @return mixed
     */
    public function _propertyGetterSetter($getSetPreffix, $name, $arguments = [])
    {
        if ($getSetPreffix === 'get') {
            return $this->$name;
        } else {
            $this->$name = $arguments[0];
            return $this;
        }
    }

    /**
     * Convert to json by default
     * 
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->getAttributes());
    }

    /**
     * Get all the non-internal attributes of the instance.
     * 
     * @return array
     */
    public function getAttributes()
    {
        $assoc = get_object_vars($this);
        $result = [];

        foreach ($assoc as $name => $value) {
            if (!$this->isInternal($name)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Get the metadata of the object.
     * 
     * @return Meta
     */
    public function meta()
    {
        return $this->_meta;
    }

    /**
     * Get the relations manager object.
     * 
     * @return RelationsManager
     */
    public function relations()
    {
        return $this->_relations;
    }

    /**
     * Determine if it's an internal attribute
     * 
     * @param string $name
     * 
     * @return boolean
     */
    public function isInternal($name)
    {
        if (strpos($name, '_') === 0) {
            return true;
        }

        if ($name === 'id') {
            return true;
        }

        return false;
    }

    /**
     * This method should be overriden for object validation.
     * 
     * @return boolean
     */
    public function valid()
    {
        return true;
    }

    // Hooks
    // Override for custom behaviors

    /**
     * Method to be executed before saving an object.
     * 
     * @return boolean
     */
    public function preSave()
    {
        return true;
    }

    /**
     * Method to be executed after saving an object.
     * 
     * @return boolean
     */
    public function postSave()
    {
        return true;
    }

    /**
     * Method to be executed before deleting an object.
     * 
     * @return boolean
     */
    public function preDelete()
    {
        return true;
    }

    /**
     * Method to be executed after deleting an object.
     * 
     * @return boolean
     */
    public function postDelete()
    {
        return true;
    }
}