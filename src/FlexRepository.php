<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Drivers\PDOMySQL;
use Makiavelo\Flex\Util\Common;

class FlexRepository
{
    public $db;

    /**
     * @var array
     */
    public $meta;

    /**
     * @var FlexRepository
     */
    private static $instance;

    /**
     * If this is set to true, no database changes will be performed.
     * @var bool
     */
    protected static $freeze = false;

    private function __construct($params = [])
    {
        if (isset($params['driver'])) {
            $this->db = $params['driver'];
        } else {
            $this->db = new PDOMySQL();
        }
    }

    /**
     * Get the current instance or create one.
     * 
     * @return FlexRepository
     */
    public static function get($params = [])
    {
        if (self::$instance) {
            return self::$instance;
        } else {
            self::$instance = new FlexRepository($params);
        }

        return self::$instance;
    }

    /**
     * Change the 'db' property to this value
     * 
     * @param mixed $driver
     * 
     * @return void
     */
    public function useDriver($driver)
    {
        $this->db = $driver;
    }

    /**
     * Freeze the database, models will no longer update the schema.
     * Recommended for production environments.
     * 
     * @return void
     */
    public static function freeze()
    {
        self::$freeze = true;
    }

    /**
     * Check if the database is frozen.
     * 
     * @return boolean
     */
    public static function frozen()
    {
        return self::$freeze;
    }

    /**
     * Re-create the instance
     * 
     * @return FlexRepository
     */
    public static function resetInstance()
    {
        self::$instance = new FlexRepository();
        return self::$instance;
    }

    /**
     * Connect to the database through the current driver
     * 
     * @param array $params
     * 
     * @return boolean
     * @throws \Exception
     */
    public function connect($params)
    {
        return $this->db->connect($params);
    }

    /**
     * Save a model (create or update)
     * if self::$freeze is true the 'prepare' method will try
     * to syncronize the current schema with the database schema.
     * 
     * preSave and postSave hooks are triggered.
     * 
     * @param Flex $model
     * 
     * @return boolean
     */
    public function save(Flex $model)
    {
        $this->beginTransaction();
        $this->prepare($model);
        $pre = $model->preSave();
        if ($pre) {
            $this->handleRelations($model);
            if ($model->isNew()) {
                $result = $this->insert($model);
            } else {
                $result = $this->update($model);
            }

            if ($result) {
                $this->handlePostRelations($model);
                $model->postSave();
                $this->commit();
                return $result;
            } else {
                $this->rollback();
                return false;
            }
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * If not frozen, start transaction
     * 
     * @return void
     */
    public function beginTransaction()
    {
        if (self::frozen()) $this->db->beginTransaction();
    }

    /**
     * If not frozen, commit transaction
     * 
     * @return void
     */
    public function commit()
    {
        if (self::frozen()) $this->db->commit();
    }

    /**
     * If not frozen, rollback transaction
     * 
     * @return void
     */
    public function rollback()
    {
        if (self::frozen()) $this->db->rollback();
    }

    /**
     * Handle relations before saving the Flex model
     * 
     * @param Flex $model
     * 
     * @return void
     */
    public function handleRelations(Flex $model)
    {
        $relations = $model->relations()->get();
        if ($relations) {
            foreach ($relations as $relation) {
                if ($relation->type === 'Belongs') {
                    if ($relation->instance) {
                        $this->handleRelations($relation->instance);
                        $this->save($relation->instance);
                        $model->{$relation->key} = $relation->instance->id;
                        $this->handlePostRelations($relation->instance);
                    }
                }
            }
        }
    }

    /**
     * Handle the relations after saving the Flex model
     * 
     * @param Flex $model
     * 
     * @return void
     */
    public function handlePostRelations(Flex $model)
    {
        $relations = $model->relations()->get();
        if ($relations) {
            foreach ($relations as $relation) {
                if ($relation->type === 'Has') {
                    $this->handleHasRelation($relation, $model);
                } elseif ($relation->type === 'HasAndBelongs') {
                    $this->handleHasAndBelongsRelation($relation, $model);
                }
            }
        }
    }

    /**
     * Handle a 'HasAndBelongs' relation after the parent model was saved
     * 
     * @param Relation $relation
     * @param Flex $model
     * 
     * @return void
     */
    public function handleHasAndBelongsRelation(Relation $relation, Flex $model)
    {
        if ($relation->isEmptyCollection()) {
            // IF the relation is an empty array then we should clear it
            // when the relation is null it means the relation wasn't loaded.
            $this->_clearIntermediateTable($model, $relation);
        } elseif ($relation->instance) {
            // Clear intermediate table records, they will be recreated
            // with the current colleciton data.
            $this->_clearIntermediateTable($model, $relation);
            foreach ($relation->instance as $key => $related) {
                // First save both ends, then add the relation record.
                $this->handleRelations($relation->instance[$key]);
                $this->save($relation->instance[$key]);
                $this->handlePostRelations($relation->instance[$key]);
                $this->_saveIntermediateTable($model, $relation, $key);
            }

            if ($relation->removeOrphans) {
                //@todo check if this is necessary, it may be costly to
                // check here for orphans...
            }
        }
    }

    /**
     * Handle a 'Has' relation after the parent model was saved
     * 
     * @param Relation $relation
     * @param Flex $model
     * 
     * @return void
     */
    public function handleHasRelation(Relation $relation, Flex $model)
    {
        if ($relation->isEmptyCollection()) {
            if ($relation->removeOrphans) {
                $this->removeUnusedChilds($model, $relation);
            } else {
                $this->nullifyUnusedChilds($model, $relation);
            }
        } elseif ($relation->instance) {
            foreach ($relation->instance as $key => $related) {
                $this->handleRelations($relation->instance[$key]);
                $relation->instance[$key]->{$relation->key} = $model->id;
                $this->save($relation->instance[$key]);
                $this->handlePostRelations($relation->instance[$key]);
            }

            if ($relation->removeOrphans) {
                $this->removeUnusedChilds($model, $relation);
            } else {
                $this->nullifyUnusedChilds($model, $relation);
            }
        }
    }

    /**
     * Internal method to clear an intermediate table in a 'HasAndBelongs'
     * relation after updates.
     * 
     * @param Flex $model
     * @param Relation $relation
     * 
     * @return boolean
     */
    public function _clearIntermediateTable(Flex $model, Relation $relation)
    {
        if ($relation->type === 'HasAndBelongs') {
            if (self::frozen()) {
                // If the db is frozen we asume the table exists.
                $tableExists = true;
            } else {
                $tableExists = $this->db->tableExists($relation->relationTable);
            }

            if ($tableExists) {
                $result = $this->db->deleteQuery($model, $relation);

                return $result;
            }
        }

        return false;
    }

    /**
     * Delete orphaned records from a 'Has' relation after an update
     * 
     * @param Flex $model
     * @param Relation $relation
     * 
     * @return boolean
     */
    public function removeUnusedChilds(Flex $model,Relation $relation)
    {
        $usedIds = array_map(function($obj) { return $obj->id; }, $relation['instance']);
        if ($relation->type === 'Has') {
            $result = $this->db->unusedChildsQuery($relation, $model, $usedIds);
        }

        return $result;
    }

    /**
     * Set to null references to the main model in a 'Has' relation after updates.
     * 
     * @param Flex $model
     * @param Relation $relation
     * 
     * @return boolean
     */
    public function nullifyUnusedChilds(Flex $model, Relation $relation)
    {
        // Extract ids from the objects collection
        $usedIds = array_map(function($obj) { return $obj->id; }, $relation->instance);

        if ($relation->type === 'Has') {
            $result = $this->db->nullifyUnusedChilds($model, $relation, $usedIds);
        }

        return $result;
    }

    /**
     * Internal method to save an intermediate table in a HasAndBelongs relationship
     * 
     * @param Flex $model original model
     * @param mixed $relation relation data array
     * @param mixed $key key in the instances collection to use
     * 
     * @return void
     */
    public function _saveIntermediateTable(Flex $model, $relation, $key)
    {
        $relationModel = new Flex();
        $relationModel->meta()->add('table', $relation->relationTable);
        $relationModel->meta()->add('table_type', 'intermediate');
        $relationModel->meta()->add('unique_pair', $relation->key . ',' . $relation->externalKey);
        $relationModel->meta()->add('fields', [
            $relation->key => ['type' => 'INT', 'nullable' => true],
            $relation->externalKey => ['type' => 'INT', 'nullable' => true],
        ]);
        $relationModel->id = null;
        $relationModel->{$relation->key} = $model->id;
        $relationModel->{$relation->externalKey} = $relation->instance[$key]->id;
        $this->save($relationModel);
    }

    /**
     * Update a model via DB query
     * Table name is retrieved from the object meta.
     * Fields and values are retrieved from the object.
     *  
     * @param Flex $model
     * 
     * @return boolean
     */
    public function update(Flex $model)
    {
        $table = $model->meta()->get('table');
        $data = $this->getFieldsAndValues($model);
        $updates = [];

        foreach ($data['fields'] as $name) {
            $updates[] = "{$name} = ?";
        }

        $values = array_merge($data['values'], [$model->id]);
        $result = $this->db->update($model, $table, $updates, $data, $values);
        return $result;
    }

    /**
     * Insert a model via DB query
     * Table name is retrieved from the object meta.
     * Fields and values are retrieved from the object.
     * 
     * @param Flex $model
     * 
     * @return boolean
     */
    public function insert(Flex $model)
    {
        $table = $model->meta()->get('table');
        $data = $this->getFieldsAndValues($model);
        $result = $this->db->insert($model, $table, $data);
        return $result;
    }

    /**
     * Delete a model.
     * Table name is retrieved from the object meta.
     * 
     * Triggers 'preDelete' and 'postDelete' events.
     * 
     * @param Flex $model
     * 
     * @return boolean
     */
    public function delete(Flex $model)
    {
        $pre = $model->preDelete();

        if ($pre) {
            $table = $model->meta()->get('table');
            $result = $this->db->delete($table, $model);

            if ($result) {
                $model->postDelete();
            }

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Save a collection of models.
     * All the models must be of the same type.
     * There can be a mix of new and not-new objects
     * insert and update operations will be performed separately.
     * 
     * The operation is atomic, transactions are used.
     * 
     * The db schema is synced.
     * 
     * @param Flex[] $collection
     * 
     * @return boolean
     */
    public function saveCollection($collection)
    {
        $status = false;
        if ($collection) {
            $this->prepare($collection[0]);
            $this->beginTransaction();
            $operations = $this->getOperations($collection);
            $status = $this->performInserts($operations['inserts']);
            if ($status) {
                $status = $this->performUpdates($operations['updates']);
            }

            if (!$status) {
                $this->rollback();
            } else {
                $this->commit();
            }
            
        }

        return $status;
    }

    /**
     * Gets an array of updates and executes them one by one.
     * 
     * @param Flex[] $updates
     * 
     * @return boolean
     */
    public function performUpdates($updates)
    {
        $status = true;

        if ($updates) {
            $status = $this->updateCollection($updates);
        }

        return $status;
    }

    /**
     * Gets an array of models to insert
     * 
     * Triggers all the 'preSave' and 'postSave' events.
     * 
     * All the models are inserted in a single query (transactional)
     * 
     * @param Flex[] $inserts
     * 
     * @return boolean
     */
    public function performInserts($inserts)
    {
        $status = true;

        if ($inserts) {
            $inserts = $this->preSaves($inserts);
            if ($inserts) {
                $status = $this->db->insertCollection($inserts);
            }
            $this->postSaves($inserts);
        }

        return $status;
    }

    /**
     * Get all the inserts/updates from a collection.
     * 
     * @param Flex[] $collection
     * 
     * @return array
     */
    public function getOperations($collection)
    {
        $ops = [
            'inserts' => [],
            'updates' => []
        ];

        foreach ($collection as $model) {
            if ($model->isNew()) {
                $ops['inserts'][] = $model;
            } else {
                $ops['updates'][] = $model;
            }
        }

        return $ops;
    }

    /**
     * Trigger 'preSave' events in the collection
     * 
     * @param Flex[] $elems
     * 
     * @return array
     */
    public function preSaves($elems)
    {
        $result = [];
        foreach ($elems as $model) {
            $pre = $model->preSave();
            if ($pre) {
                $result[] = $model;
            }
        }

        return $result;
    }

    /**
     * Execute all the 'postSave' hooks from a model collection
     * 
     * @param array $elems
     * 
     * @return void
     */
    public function postSaves($elems)
    {
        foreach ($elems as $model) {
            $model->postSave();
        }
    }

    /**
     * Update all the models from a collection
     * 
     * @param Flex[] $updates
     * 
     * @return boolean
     */
    public function updateCollection($updates)
    {
        if ($updates) {
            $this->beginTransaction();
            foreach($updates as $model) {
                $status = $this->save($model);
                if (!$status) {
                    $this->rollback();
                    return false;
                }
            }
            $this->commit();
        }

        return true;
    }

    /**
     * Get all the fields and their values from a Flex model
     * 
     * @param Flex $model
     * 
     * @return array
     */
    public function getFieldsAndValues(Flex $model)
    {
        $attrs = $model->getAttributes();
        $result = [
            'fields' => [],
            'values' => []
        ];

        foreach ($attrs as $name => $value) {
            $result['fields'][] = $name;
            $result['values'][] = $value;
        }

        return $result;
    }

    

    /**
     * Get an array of ids from a Flex collection
     * 
     * @param Flex[] $collection
     * 
     * @return array
     */
    public function getCollectionIds($collection)
    {
        $ids = [];
        foreach ($collection as $model) {
            $ids[] = $model->id;
        }

        return $ids;
    }

    /**
     * While not frozen the table will get updated
     * just by saving records to it. 
     * The Flex instance may have a meta definition for fields, which
     * will be applied to the table.
     * If no meta was defined for fields, non-existant fields will be
     * created as TEXT fields in the table.
     * 
     * @param Flex $model
     * 
     * @return boolean
     */
    public function prepare(Flex $model)
    {
        if (self::frozen() === true) {
            return true;
        }

        $table = $model->meta()->get('table');
        $tableExists = $this->db->tableExists($table);

        if (!$tableExists) {
            $this->db->createTable($table);
        }

        $tableFields = $this->db->getTableFields($table);

        $this->db->updateTableTypes($model, $tableFields);
        $fields = $this->addModelFields($model, $tableFields);
        $fields = $this->addNewFields($model, $tableFields, $fields);

        if ($fields) {
            $this->db->addFieldsToTable($table, $fields);
        }

        // It was actually created, but this var was false if 
        // it didn't exist in the begining of the script.
        if (!$tableExists) {
            $type = $model->meta()->get('table_type');
            if ($type === 'intermediate') {
                $this->db->addUniqueCombinedIndex($model);
            }
        }

        return true;
    }

    

    /**
     * Add new fields to the table if needed.
     * 
     * @param Flex $model
     * @param mixed $tableFields
     * @param array $fields
     * 
     * @return array
     */
    public function addNewFields(Flex $model, $tableFields, $fields = [])
    {
        $data = $this->getFieldsAndValues($model);

        foreach ($data['fields'] as $key => $name) {
            
            $foundInTable = Common::find($tableFields, 'Field', $name);
            $foundInFields = Common::find($fields, 'name', $name);
            $found = $foundInTable || $foundInFields;

            if (!$found) {
                $fields[] = [
                    'name' => $name,
                    'type' => 'TEXT',
                    'null' => true
                ];
            }
        }

        return $fields;
    }

    /**
     * Add the model fields defined in the meta that are not defined
     * in the database table.
     * 
     * @param Flex $model
     * @param array $tableFields
     * @param array $fields
     * 
     * @return array
     */
    public function addModelFields(Flex $model, $tableFields, $fields = [])
    {
        $modelFields = $model->meta()->get('fields');
        if ($modelFields) {
            foreach ($modelFields as $name => $modelData) {
                $found = Common::find($tableFields, 'Field', $name);

                if (!$found) {
                    $fields[] = [
                        'name' => $name,
                        'type' => $modelData['type'],
                        'null' => $modelData['nullable']
                    ];
                }
            }
        }

        return $fields;
    }

    

    

    /**
     * Helper method to create a new object
     * If an object is created this way, all the fields
     * will be created as TEXT by default.
     * 
     * @param string $table
     * 
     * @return Flex
     */
    public function create($table)
    {
        $flex = new Flex();
        $flex->meta()->add('table', $table);
        $flex->id = null;

        return $flex;
    }

    /**
     * Execute any query and get an array or a collection of models
     * If the query has any parameters, they have to be prepared to avoid injection
     * so name them in the PDO fashion and send an array of parameters/values.
     * 
     * Eg:
     *   $query = 'SELECT * FROM user WHERE id = :id';
     *   $params = [':id' => $id];
     * 
     * In the $options parameter define if you want hydration, which is enabled by default
     * to return a collection of Flex models.
     * 
     * In the case of Flex models, if the table name option is not defined, then the models can't
     * be updated later on (unless the table meta is set manually).
     * 
     * @param mixed $query
     * @param mixed $params
     * @param array $options
     * 
     * @return array
     */
    public function query($query, $params = [], $options = [])
    {
        $result = $this->db->query($query, $params, $options);

        if ($result) {
            if (Common::get($options, 'hydrate', true)) {
                $result = $this->hydrate($result, Common::get($options, 'table', ''), Common::get($options, 'class', 'Makiavelo\\Flex\\flex'));
            }
        }

        return $result;
    }

    /**
     * Helper method to do quick finds
     * For complex queries use PDO directly.
     * 
     * @param string $table
     * @param string $condition
     * @param array $params
     * @param array $options
     * 
     * @return array|false
     */
    public function find($table, $condition = '', $params = [], $options = [])
    {
        $result = $this->db->find($table, $condition, $params, $options);
        if (Common::get($options, 'hydrate', true)) {
            $result = $this->hydrate($result, $table, Common::get($options, 'class', 'Makiavelo\\Flex\\Flex'));
        }

        return $result;
    }

    /**
     * Alias for 'find' which just returns the first result and
     * limits the query to one result.
     * 
     * @param mixed $table
     * @param string $condition
     * @param array $params
     * @param array $options
     * 
     * @return mixed
     */
    public function findOne($table, $condition = '', $params = [], $options = [])
    {
        $result = $this->db->findOne($table, $condition, $params, $options);
        if ($result) {
            if ($options['hydrate']) {
                $result = $this->hydrate($result[0], $table, $options['class']);
            }
        }

        return $result;
    }

    /**
     * Hydrate a collection.
     * By default all the results are Hydrated to a collection of Flex
     * but the class to use can be modified.
     * 
     * @param array $result
     * @param string $class
     * 
     * @return array
     */
    public function hydrate($result, $table, $class = 'Makiavelo\\Flex\\Flex')
    {
        if ($result) {
            $mainId = Common::get($result, '0->' . $table . '.id');
            $hydrated = [];

            // Build an array of arrays grouping by id
            // Each group will be hydrated as one object of the selected class
            $haystack = $this->buildHaystack($result, $table, $mainId);

            if ($haystack) {
                foreach ($haystack as $block) {
                    $model = new $class();
                    if ($class === 'Makiavelo\\Flex\\Flex' && $table) {
                        $model->meta()->add('table', $table);
                    }

                    $hydrated[] = $model->hydrate($block);
                }
            }

            return $hydrated;
        }

        return $result;
    }

    /**
     * Get a full result from the database, and group them by the
     * selected id.
     * 
     * @param array $result
     * @param string $table
     * @param mixed $id
     * 
     * @return array
     */
    public function buildHaystack($result, $table, $id)
    {
        $haystack = [];
        $tree = [];

        foreach ($result as $item) {
            $rowId = Common::get($item, $table . '.id');
            if ($rowId && $rowId === $id) {
                $tree[] = $item;
            } else {
                if (!$rowId) {
                    $haystack[] = $item;
                } else {
                    $haystack[] = $tree;
                    $tree = [$item];
                    $id = $rowId;
                }
            }
        }

        if ($tree) {
            $haystack[] = $tree;
            $tree = [];
        }

        return $haystack;
    }
}