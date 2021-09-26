<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Util\EnhancedPDO;
use Makiavelo\Flex\Util\Common;

class FlexRepository
{
    /**
     * @var EnhancedPDO
     */
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
    public static $freeze = false;

    private function __construct()
    {
        
    }

    /**
     * Get the current instance or create one.
     * 
     * @return FlexRepository
     */
    public static function get()
    {
        if (self::$instance) {
            return self::$instance;
        } else {
            self::$instance = new FlexRepository();
        }

        return self::$instance;
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
     * Connect to the database
     * 
     * @param string $host
     * @param string $db
     * @param string $user
     * @param string $pass
     * @param string $charset
     * 
     * @return boolean
     * @throws \PDOException
     */
    public function connect($host, $db, $user, $pass, $charset = 'utf8mb4')
    {
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->db = new EnhancedPdo($dsn, $user, $pass, $options);
            return true;
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
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
        $this->prepare($model);
        $pre = $model->preSave();
        if ($pre) {
            if ($model->isNew()) {
                $result = $this->insert($model);
            } else {
                $result = $this->update($model);
            }

            if ($result) {
                $model->postSave();
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
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
        $table = $model->getMeta('table');
        $data = $this->getFieldsAndValues($model);
        $updates = [];

        foreach ($data['fields'] as $name) {
            $updates[] = "{$name} = ?";
        }

        $query = "UPDATE {$table} SET " . implode(",", $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $values = array_merge($data['values'], [$model->id]);
        $result = $stmt->execute($values);

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
        $table = $model->getMeta('table');
        $data = $this->getFieldsAndValues($model);
        $count = count($data['fields']);
        $placeHolders = array_fill(0, $count, '?');

        $query = "INSERT INTO {$table} (" . implode(",", $data['fields']) . ") VALUES (" . implode(",", $placeHolders) . ")";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($data['values']);
        if ($result) {
            $id = $this->db->lastInsertId();
            $model->setId($id);
        }
        
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
            $table = $model->getMeta('table');
            $query = "DELETE FROM {$table} WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$model->id]);

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
            $this->db->beginTransaction();
            $operations = $this->getOperations($collection);
            $status = $this->performInserts($operations['inserts']);
            if ($status) {
                $status = $this->performUpdates($operations['updates']);
            }

            if (!$status) {
                $this->db->rollback();
            } else {
                $this->db->commit();
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
                $status = $this->insertCollection($inserts);
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
            $this->db->beginTransaction();
            foreach($updates as $model) {
                $status = $this->save($model);
                if (!$status) {
                    $this->db->rollback();
                    return false;
                }
            }
            $this->db->commit();
        }

        return true;
    }

    /**
     * Insert a model collection in the database using transactions
     * All the models are inserted in one query.
     * 
     * @param Flex[] $inserts
     * 
     * @return boolean
     * @throws \PDOException
     */
    public function insertCollection($inserts) {
        $model = $inserts[0];
        $table = $model->getMeta('table');
        $data = $this->getFieldsAndValues($model);
        $count = count($data['fields']);

        $insertStrings = [];
        $values = [];
        foreach ($inserts as $m) {
            $placeHolders = array_fill(0, $count, '?');
            $insertStrings[] = "(" . implode(',', $placeHolders) . ")";
            $fv = $this->getFieldsAndValues($m);
            foreach ($fv['values'] as $key => $value) {
                $values[] = $value;
            }
        }

        $this->db->beginTransaction();
        $query = "INSERT INTO {$table} (" . implode(',', $data['fields']) . ") VALUES " . implode(',', $insertStrings);
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($values);
        $lastId = $this->db->lastInsertId();

        for ($i = 0; $i < count($inserts); $i++) {
            $inserts[$i]->setId($lastId + $i);
        }

        $this->db->commit();

        return $result;
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
     * Delete a collection of Flex models
     * 
     * @param Flex[] $collection
     * 
     * @return boolean
     */
    public function deleteCollection($collection)
    {
        if ($collection) {
            $model = $collection[0];
            $table = $model->getMeta('table');
            $count = count($collection);
            $placeHolders = array_fill(0, $count, '?');

            $query = "DELETE FROM {$table} WHERE id IN (" . implode(',', $placeHolders) . ")";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($this->getCollectionIds($collection));

            return $result;
        } else {
            return false;
        }
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
     * While not freezed the table will get updated
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
        if (FlexRepository::$freeze === true) {
            return true;
        }

        $table = $model->getMeta('table');
        $tableExists = $this->tableExists($table);

        if (!$tableExists) {
            $this->createTable($table);
        }

        $tableFields = $this->getTableFields($table);

        $this->updateTableTypes($model, $tableFields);
        $fields = $this->addModelFields($model, $tableFields);
        $fields = $this->addNewFields($model, $tableFields, $fields);

        if ($fields) {
            $this->addFieldsToTable($table, $fields);
        }

        return true;
    }

    /**
     * Update field types on the model table
     * 
     * @param Flex $model
     * @param mixed $tableFields
     * 
     * @return void
     */
    public function updateTableTypes(Flex $model, $tableFields)
    {
        $table = $model->getMeta('table');
        $baseQuery = "ALTER TABLE `{$table}` MODIFY COLUMN ";
        $modelFields = $model->getMeta('fields');
        if ($modelFields) {
            foreach ($modelFields as $name => $modelData) {
                foreach ($tableFields as $tableField) {
                    if ($tableField['Field'] === $name) {
                        if (strtolower($tableField['Type']) !== strtolower($modelData['type'])) {
                            $query = $baseQuery . "`{$name}`" . " " . $modelData['type'];
                            $this->db->query($query);
                        }       
                    }
                }
            }
        }
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
        $modelFields = $model->getMeta('fields');
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
     * Execute the query to add fields to a table
     * 
     * @param string $name Table name
     * @param array $fields
     * 
     * @return \PDOStatement|false
     */
    public function addFieldsToTable($name, $fields)
    {
        $query = "ALTER TABLE {$name} ";
        $columns = [];
        foreach ($fields as $key => $field) {
            $fieldQuery = "ADD COLUMN {$field['name']} {$field['type']}";
            if (!$field['null']) {
                $fieldQuery .= " NOT NULL";
            }
            $columns[] = $fieldQuery;
        }

        $query .= implode(',', $columns);
        $result = $this->db->query($query);

        return $result;
    }

    /**
     * Execute the query to create a table
     * 
     * @param string $name
     * 
     * @return \PDOStatement|false
     */
    public function createTable($name) {
        $tableQuery = "CREATE TABLE IF NOT EXISTS `{$name}` (id INT auto_increment, primary key (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $result = $this->db->query($tableQuery);

        return $result;
    }

    /**
     * Check if a table exists via query
     * 
     * @param string $name
     * 
     * @return boolean
     */
    public function tableExists($name)
    {
        $query = "SHOW TABLES LIKE '{$name}'";
        $result = $this->db->query($query)->fetchAll();
        if (count($result) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Get the table fields via DESCRIBE query
     * 
     * @param string $name
     * 
     * @return array|false
     */
    public function getTableFields($name)
    {
        $query = "DESCRIBE {$name}";
        $result = $this->db->query($query)->fetchAll();

        return $result;
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
        $flex->addMeta('table', $table);
        $flex->id = null;

        return $flex;
    }

    /**
     * Helper method to do quick finds
     * For complex queries use PDO directly.
     * Hydration has to be done manually if needed.
     * 
     * @param mixed $table
     * @param mixed $condition
     * 
     * @return array|false
     */
    public function find($table, $condition)
    {
        $query = "SELECT * FROM {$table} WHERE {$condition}";
        $result = $this->db->query($query)->fetchAll();

        return $result;
    }
}