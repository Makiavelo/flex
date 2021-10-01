<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Util\Common;

/**
 * Flexible models
 * 
 * @todo add aliases to relationships to be able to hydrate when there are 2 of the same kind (Eg: person (lawyer, doctor)) (DONE)
 * @todo self references (DONE)
 * @todo cleanup, make methods shorter, optimize algorithms, beautify (Done partially, no algos changed, just moved to sub methods)
 * @todo Evaluate Many-to-Many (DONE)
 * @todo Check for updates/deletes in child collections
 * @todo Check for HasAndBelongs what happens if it has the same 'tag' twice
 */
class Flex
{
    /**
     * Internal variable for meta data like table name
     * @var array
     */
    public $_meta;

    public function __construct()
    {

    }

    /**
     * Convert an array/object result to this class
     * this class can be Flex or any class that extended it
     * 
     * @param mixed $data
     * 
     * @return mixed
     */
    public static function build($data)
    {
        $class = static::class;
        $model = new $class();
        $model->hydrate($data);

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
     * @param mixed $data The array/object to hydrate
     * @param string $alias Alias used
     * 
     * @return Flex
     */
    public function hydrate($data, $alias = '')
    {
        if (!is_array($data)) {
            // Convert to associative array
            $data = json_decode(json_encode($data), true);
        }
        
        // Get the first result if it's a collection, or
        // just de values if it's an associative.
        $dataIsCollection = Common::isCollection($data);
        $mainData = $dataIsCollection ? $data[0] : $data;

        if (get_class($this) === 'Makiavelo\\Flex\\Flex') {
            $this->hydrateFlex($mainData);
        } else {
            $hydrated = $this->hydrateCustomClass($mainData, $alias);

            if ($hydrated) {
                // Force a collection format
                $dataCollection = $dataIsCollection ? $data : [$data];

                // Remove own fields to prevent circular dependencies
                $modified = $this->removeTableFields($alias, $dataCollection);

                $this->hydrateRelations($modified);
            }
        }

        return $this;
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
        $aliasOrTable = $this->_getAliasOrTable($alias);
        
        // Loop all the rows
        foreach ($data as $index => $row) {

            // Loop each field
            foreach ($row as $field => $value) {

                // Extract table and field name
                $parts = explode('.', $field);
                if (count($parts) === 2) {

                    // If the table name matches the current alias/table
                    // then remove it from the collection.
                    if ($parts[0] === $aliasOrTable) {
                        unset($data[$index][$field]);

                        // If a row is empty, remove it.
                        if (!$data[$index]) {
                            unset($data[$index]);
                        }
                    }
                }
            }
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
            $aliasOrTable = $this->_getAliasOrTable($alias);
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
     * Helper method to determine if an alias or a table
     * name should be used.
     * 
     * @param mixed $alias
     * 
     * @return string
     */
    public function _getAliasOrTable($alias)
    {
        if ($alias) {
            return $alias;
        } elseif ($this->getMeta('table')) {
            return $this->getMeta('table');
        }

        return '';
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
            $parts = explode('.', $attr);
            if (count($parts) === 2) {
                $attrName = $parts[1];
            } else {
                $attrName = $parts[0];
            }

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
            $relations = $this->getMeta('relations');
            if ($relations) {
                foreach ($relations as $relation) {
                    if ($relation['type'] === 'Belongs') {
                        $this->hydrateBelongs($relation, $data);
                    } elseif ($relation['type'] === 'Has') {
                        if ($data) {
                            $this->hydrateHas($relation, $data);
                        }
                    } elseif ($relation['type'] == 'HasAndBelongs') {
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
            $model = new $relation['class']();
            $model->hydrate($row);

            if (!$model->isEmpty()) {
                if (is_array($relation['instance'])) {
                    $relation['instance'][] = $model;
                } else {
                    $relation['instance'] = [];
                    $relation['instance'][] = $model;
                }

                $relation['loaded'] = true;
                $this->editRelation($relation['name'], $relation);
            }
        }
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
        $related = new $relation['class'];
        $related->hydrate($data, $relation['table_alias']);
        $this->{$relation['key']} = $related->id;
        if (!$related->isEmpty()) {
            $relation['instance'] = $related;
            $relation['loaded'] = true;
        }
        $this->editRelation($relation['name'], $relation);
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
     * All the relationships information is taken from
     * $this->_meta['relations']
     * 
     * @param mixed $name
     * @param mixed $arguments
     * 
     * @return [type]
     */
    public function __call($name, $arguments)
    {
        // Check if it's a getter/setter
        $getSetPreffix = substr($name, 0, 3);
        if (in_array($getSetPreffix, ['get', 'set'])) {

            // Determine both cases (sname & camel) for the attribute name
            $camelCaseAttr = substr($name, 3);
            $snakeCaseAttr = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCaseAttr));

            // If the property exists, get or set it
            if (property_exists($this, $snakeCaseAttr)) {
                return $this->_propertyGetterSetter($getSetPreffix, $snakeCaseAttr, $arguments);

            } elseif ($this->hasRelation($camelCaseAttr)) {
                // Search for relationships
                $relation = $this->getRelation($camelCaseAttr);
                if ($getSetPreffix === 'get') {
                    return $this->_relationGetter($relation);
                } else {
                    $this->_relationSetter($relation, $arguments);
                    return $this;
                }
            } else {
                throw new \Exception("Undefined attribute '" . $snakeCaseAttr . "'");
            }
        } else {
            throw new \Exception("Undefined method '" . $name . "'");
        }
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
    public function _propertyGetterSetter($getSetPreffix, $snakeCaseAttr, $arguments = [])
    {
        if ($getSetPreffix === 'get') {
            return $this->$snakeCaseAttr;
        } else {
            $this->$snakeCaseAttr = $arguments[0];
            return $this;
        }
    }

    /**
     * Internal method to set a relationship
     * 
     * @param mixed $relation
     * @param mixed $arguments
     * 
     * @return void
     */
    public function _relationSetter($relation, $arguments)
    {
        if ($relation['type'] === 'Belongs') {
            $this->_setBelongInstance($relation, $arguments);
        } elseif ($relation['type'] === 'Has') {
            $this->_setHasInstance($relation, $arguments);
        } elseif ($relation['type'] === 'HasAndBelongs') {
            $this->_setHasInstance($relation, $arguments);
        }
    }

    /**
     * Internal method to get a relationship
     * 
     * @param mixed $relation
     * 
     * @return mixed
     * @throws \Exception
     */
    public function _relationGetter($relation)
    {
        if ($relation['type'] === 'Belongs') {
            return $this->_getBelongRelationInstance($relation);
        } elseif ($relation['type'] === 'Has') {
            return $this->_getHasRelationInstance($relation);
        } elseif ($relation['type'] === 'HasAndBelongs') {
            return $this->_getHasAndBelongsRelationInstance($relation);
        } else {
            throw new \Exception("Relation type not supported ('" . $relation['type'] . "')");
        }
    }

    /**
     * Internal method to set a 'Has' instance (collection)
     * 
     * @param mixed $relation
     * @param mixed $arguments
     * 
     * @return void
     */
    public function _setHasInstance($relation, $arguments)
    {
        $relation['loaded'] = true;
        $relation['instance'] = $arguments[0];
        $this->editRelation($relation['name'], $relation);
    }

    /**
     * Internal method to set a 'Belong' relation instance
     * 
     * @param mixed $relation
     * @param mixed $arguments
     * 
     * @return void
     */
    public function _setBelongInstance($relation, $arguments)
    {
        $relation['loaded'] = true;
        $relation['instance'] = $arguments[0];
        $this->editRelation($relation['name'], $relation);
    }

    /**
     * Get or Load the other end of a 'HasAndBelongs' relationship
     * 
     * @param mixed $relation
     * 
     * @return array
     */
    public function _getHasAndBelongsRelationInstance($relation)
    {
        if ($relation['loaded']) {
            return $relation['instance'];
        } else {
            $repo = FlexRepository::get();
            $query = "SELECT {$relation['table']}.* FROM {$relation['table']} JOIN {$relation['relation_table']} ON {$relation['relation_table']}.{$relation['external_key']} = {$relation['table']}.id WHERE {$relation['relation_table']}.{$relation['key']} = :id";
            $models = $repo->query($query, [], ['table' => $relation['table'], 'class' => $relation['class']]);

            if ($models) {
                $relation['loaded'] = true;
                $relation['instance'] = $models;
                $this->editRelation($relation['name'], $relation);
                return $relation['instance'];
            }
        }

        return $relation['instance'];
    }

    /**
     * Internal method to get a collection of 'Has' relation instances.
     * 
     * @param mixed $relation
     * 
     * @return mixed
     */
    public function _getHasRelationInstance($relation)
    {
        if ($relation['loaded']) {
            return $relation['instance'];
        } else {
            $repo = FlexRepository::get();
            $models = $repo->find(
                $relation['table'],
                "{$relation['key']} = :id",
                [':id' => $this->id],
                ['class' => $relation['class']
            ]);

            if ($models) {
                $relation['loaded'] = true;
                $relation['instance'] = $models;
                $this->editRelation($relation['name'], $relation);
                return $relation['instance'];
            }
        }

        return $relation['instance'];
    }

    /**
     * Internal method to get a 'Belong' relationship's instance
     * 
     * @param mixed $relation
     * 
     * @return [type]
     */
    public function _getBelongRelationInstance($relation)
    {
        if ($relation['loaded']) {
            return $relation['instance'];
        } else {
            $repo = FlexRepository::get();
            $model = $repo->findOne(
                $relation['table'],
                'id = :id',
                [':id' => $this->{$relation['key']}],
                ['class' => $relation['class']
            ]);

            if ($model) {
                $relation['loaded'] = true;
                $relation['instance'] = $model;
                $this->editRelation($relation['name'], $relation);
            }
        }

        return $relation['instance'];
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
     * Add a relation to another model
     * $param
     * @param mixed $params
     * 
     * @return void
     */
    public function addRelation($params = [])
    {
        $params = array_merge($this->getDefaultRelationParams(), $params);
        $params = $this->setRelationDefaults($params);

        $this->validateParams($params);
        $relations = $this->getMeta('relations');
        $relations[$params['name']] = $params;
        $this->addMeta('relations', $relations);
    }

    /**
     * Validate all the parameters of a relation
     * 
     * @param mixed $params
     * 
     * @return void
     * @throws \Exception
     */
    public function validateParams($params)
    {
        if (!$params['name']) {
            throw new \Exception('Flex relations require a name');
        }

        if ($this->hasRelation($params['name'])) {
            throw new \Exception('This relation already exists');
        }

        if (!$params['class'] || !class_exists($params['class'])) {
            throw new \Exception('Flex relations require a class');
        }

        if (!$params['table']) {
            throw new \Exception('Flex relations require a table name');
        }
    }

    /**
     * Get a relation by name
     * 
     * @param mixed $name
     * 
     * @return null|array
     */
    public function getRelation($name) {
        $relations = $this->getMeta('relations');
        return Common::get($relations, $name);
    }

    /**
     * Convenience method to check if the instance has a 
     * relationship
     * 
     * @param mixed $name
     * 
     * @return boolean
     */
    public function hasRelation($name)
    {
        return !!$this->getRelation($name);
    }

    /**
     * Overwrite a relation info with a new one.
     * 
     * @param mixed $name
     * @param mixed $params
     * 
     * @return boolean
     */
    public function editRelation($name, $params)
    {
        foreach ($this->_meta['relations'] as $relName => $relation) {
            if ($name === $relName) {
                $this->_meta['relations'][$name] = $params;
                return true;
            }
        }

        return false;
    }

    /**
     * Set the default parameters of a relation
     * 
     * @param mixed $params
     * 
     * @return array
     */
    public function setRelationDefaults($params)
    {
        if (!$params['key'] && $params['name']) {
            $snakeCaseName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $params['name']));
            $params['name'] = $snakeCaseName . '_id';
        }

        $params['loaded'] = false;
        $params['instance'] = null;

        return $params;
    }

    /**
     * Get the default relation parameters
     * 
     * @return array
     */
    public function getDefaultRelationParams()
    {
        return [
            'name' => '',
            'key' => '',
            'table_alias' => '',
            'class' => 'Makiavelo\\Flex\\Flex',
            'type' => 'Belongs'
        ];
    }

    /**
     * Get the metadata of the object.
     * @return array
     */
    public function _meta()
    {
        return $this->_meta;
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
     * Add values to the object meta-data
     * 
     * @param string $name
     * @param mixed $value
     * 
     * @return void
     */
    public function addMeta($name, $value = null)
    {
        $this->_meta[$name] = $value;
    }

    /**
     * Get a meta-data value
     * 
     * @param string $path
     * @param mixed $default
     * 
     * @return mixed
     */
    public function getMeta($path, $default = null)
    {
        return Common::get($this->_meta, $path, $default);
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