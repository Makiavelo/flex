<?php

namespace Makiavelo\Flex\Drivers;

use Makiavelo\Flex\Drivers\EnhancedPDO;
use Makiavelo\Flex\Flex;
use Makiavelo\Flex\Relation;
use Makiavelo\Flex\Util\Common;

class PDOMySQL
{
    public $db;

    public function __construct()
    {

    }

    /**
     * Connect to PDO
     * 
     * @param mixed $params
     * 
     * @return boolean
     * @throws \PDOException
     */
    public function connect($params)
    {
        $charset = Common::get($params, 'charset', 'utf8mb4');
        $dsn = "mysql:host={$params['host']};dbname={$params['db']};charset={$charset}";
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->db = new EnhancedPdo($dsn, $params['user'], $params['pass'], $options);
            return true;
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Query used to delet unused childs in 'Has' or 'HasAndBelongs' relations
     * 
     * @param Relation $relation
     * @param Flex $model
     * @param mixed $ids
     * 
     * @return [type]
     */
    public function unusedChildsQuery(Relation $relation, Flex $model, $ids)
    {
        $query = "DELETE FROM {$relation->table} WHERE {$relation->key} = :parent_id AND {$relation->table}.id NOT IN (" . implode($ids) . ")";
        $stmt = $this->db->prepare($query);
        $this->bindValues($stmt, [':parent_id' => $model->id]);
        $result = $stmt->execute();

        return $result;
    }

    /**
     * Query to set to null childs of a 'Has' relation
     * 
     * @param Flex $model
     * @param Relation $relation
     * @param mixed $ids
     * 
     * @return boolean
     */
    public function nullifyUnusedChilds(Flex $model, Relation $relation, $ids)
    {
        $query = "UPDATE {$relation->table} SET {$relation->key} = NULL WHERE {$relation->key} = :parent_id";
        if ($ids) {
            $query .= " AND {$relation->table}.id NOT IN (" . implode(',', $ids) . ")";
        }
        
        $stmt = $this->db->prepare($query);
        $this->bindValues($stmt, [':parent_id' => $model->id]);
        $result = $stmt->execute();

        return $result;
    }

    /**
     * Query to update a model
     * 
     * @param mixed $model
     * @param mixed $table
     * @param mixed $updates
     * @param mixed $data
     * @param mixed $values
     * 
     * @return [type]
     */
    public function update($model, $table, $updates, $data, $values)
    {
        $query = "UPDATE {$table} SET " . implode(",", $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($values);

        return $result;
    }

    /**
     * Query to insert a model
     * Sets the inserted ID in the model instance.
     * 
     * @param Flex $model
     * @param mixed $table
     * @param mixed $data
     * 
     * @return boolean
     */
    public function insert(Flex $model, $table, $data)
    {
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
     * Query to delete a record by id
     * 
     * @param mixed $table
     * @param Flex $model
     * 
     * @return boolean
     */
    public function delete($table, Flex $model)
    {
        $query = "DELETE FROM {$table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([$model->id]);
        return $result;
    }

    /**
     * Query to delete from a relation table
     * 
     * @param Flex $model
     * @param Relation $relation
     * 
     * @return boolean
     */
    public function deleteQuery(Flex $model, Relation $relation)
    {
        $query = "DELETE FROM {$relation->relationTable} WHERE {$relation->key} = :parent_id";
        $stmt = $this->db->prepare($query);
        $this->bindValues($stmt, [':parent_id' => $model->id]);
        $result = $stmt->execute();
        return $result;
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
        $table = $model->meta()->get('table');
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

        $this->beginTransaction();
        $query = "INSERT INTO {$table} (" . implode(',', $data['fields']) . ") VALUES " . implode(',', $insertStrings);
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($values);
        $lastId = $this->db->lastInsertId();

        for ($i = 0; $i < count($inserts); $i++) {
            $inserts[$i]->setId($lastId + $i);
        }

        $this->commit();

        return $result;
    }

    /**
     * Get all the fields and their values from a Flex model
     * 
     * @param Flex $model
     * 
     * @return array
     */
    public function getFieldsAndValues($model)
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
            $table = $model->meta()->get('table');
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
     * Query to add the unique key constraint on a relation table
     * 
     * @param Flex $model
     * 
     * @return boolean
     */
    public function addUniqueCombinedIndex(Flex $model)
    {
        $query = "ALTER TABLE `{$model->meta()->get('table')}` ADD CONSTRAINT flex_unique_pair_constraint UNIQUE KEY({$model->meta()->get('unique_pair')})";
        $result = $this->db->query($query);

        return $result;
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
        $table = $model->meta()->get('table');
        $baseQuery = "ALTER TABLE `{$table}` MODIFY COLUMN ";
        $modelFields = $model->meta()->get('fields');
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
        $this->db->setAttribute(\PDO::ATTR_FETCH_TABLE_NAMES, true);
        $options = array_merge($this->getDefaultOptions(), $options);

        $query = "SELECT * FROM {$table}";
        if ($condition) {
            $query .= " WHERE {$condition}";
            $stmt = $this->db->prepare($query);
            $this->bindValues($stmt, $params);
            $stmt->execute();
            $result = $stmt->fetchAll();
        } else {
            $result = $this->db->query($query)->fetchAll();
        }

        $this->db->setAttribute(\PDO::ATTR_FETCH_TABLE_NAMES, false);

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
        $condition .= ' LIMIT 1';
        $result = $this->find($table, $condition, $params, $options);
        if ($result) {
            return $result[0];
        }

        return null;
    }

    /**
     * Get default options for queries
     * 
     * @return array
     */
    public function getDefaultOptions()
    {
        $options = [
            'hydrate' => true,
            'class' => 'Makiavelo\\Flex\\Flex',
            'table' => ''
        ];

        return $options;
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
        try {
            $this->db->setAttribute(\PDO::ATTR_FETCH_TABLE_NAMES, true);
            $options = array_merge($this->getDefaultOptions(), $options);

            $stmt = $this->db->prepare($query);
            $this->bindValues($stmt, $params);
            $stmt->execute();
            $result = $stmt->fetchAll();
            $this->db->setAttribute(\PDO::ATTR_FETCH_TABLE_NAMES, false);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * If not frozen, start transaction
     * 
     * @return void
     */
    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    /**
     * If not frozen, commit transaction
     * 
     * @return void
     */
    public function commit()
    {
        $this->db->commit();
    }

    /**
     * If not frozen, rollback transaction
     * 
     * @return void
     */
    public function rollback()
    {
        $this->db->rollback();
    }

    /**
     * Bind parameters to the PDOStatement
     * 
     * @param \PDOStatement $stmt
     * @param array $params
     * 
     * @return void
     */
    public function bindValues(\PDOStatement $stmt, $params = [])
    {
        if ($params) {
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
        }
    }
}