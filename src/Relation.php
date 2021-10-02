<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Util\Common;
use Makiavelo\Flex\FlexRepository;
use Makiavelo\Flex\Flex;

/**
 * Class to handle all the relationship information
 * of a Flex model.
 */
class Relation
{
    /**
     * Name of the relationship
     * It will be indexed by that name in RelationManager
     * @var string
     */
    public $name;

    /**
     * The key to use in the relationship
     * Belongs: will be the local field
     * Has: will be the remote field
     * HasAndBelongs: will be the local field name in the relation table
     * @var string
     */
    public $key;

    /**
     * Used in 'HasAndBelongs' relations
     * It's the id of the other entity in the relation table
     * @var string
     */
    public $externalKey;

    /**
     * The table name of the model
     * @var string
     */
    public $table;

    /**
     * When using multiple references to the same table
     * an alias is required.
     * @var string
     */
    public $tableAlias;

    /**
     * Used in 'HasAndBelongs' relationships
     * It's the name of the intermediate table
     * @var string
     */
    public $relationTable;

    /**
     * The name of the class of the related model
     * @var string
     */
    public $class = 'Makiavelo\\Flex\\Flex';

    /**
     * The type of relationship
     * Possible values: 'Belongs', 'Has' and 'HasAndBelongs'
     * @var string
     */
    public $type = 'Belongs';

    /**
     * Whether to remove orphaned records or not after a 'Has' or
     * a 'HasAndBelongs' relation records are updated.
     * @var bool
     */
    public $removeOrphans = false;
    
    /**
     * The instance of the related model/s
     * This can be a class instance or an array in case of 'Has' and
     * 'HasAndBelongs' relations.
     * @var null|object|array
     */
    public $instance = null;

    /**
     * Flag to check if the instance was loaded
     * If a 'Has' or 'HasAndBelongs' relation is set to an empty array
     * it will count as loaded, since it was just created.
     * @var bool
     */
    public $loaded = false;
    
    /**
     * Name of the unique pair of field names used in a 'HasAndBelongs' 
     * intermediate table.
     * @var string
     */
    public $uniquePair;

    public function __construct($params = [])
    {
        $this->build($params);
    }

    /**
     * Set all the object's parameters via array input
     * 
     * @param array $params
     * 
     * @return Relation
     */
    public function build($params = [])
    {
        $this->validateParams($params);

        $this->name = Common::get($params, 'name');
        $this->key = Common::get($params, 'key');
        $this->externalKey = Common::get($params, 'external_key');
        $this->table = Common::get($params, 'table');
        $this->tableAlias = Common::get($params, 'table_alias');
        $this->class = Common::get($params, 'class', $this->class);
        $this->type = Common::get($params, 'type', $this->type);
        $this->removeOrphans = Common::get($params, 'remove_orphans', false);
        $this->instance = Common::get($params, 'instance', null);
        $this->uniquePair = Common::get($params, 'unique_pair');
        $this->relationTable = Common::get($params, 'relation_table');

        return $this;
    }

    /**
     * Convenience method to set some default values if some required
     * parameters are missing.
     * 
     * @return Relation
     */
    public function initUnsetFields()
    {
        if (!$this->key && $this->name) {
            $snakeCaseName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->name));
            $this->key = $snakeCaseName . '_id';
        }

        return $this;
    }

    /**
     * Validate that all the required parameters for a relationship are set
     * 
     * @param array $params
     * 
     * @return Relation
     * @throws \Exception
     */
    public function validateParams($params)
    {
        if (!$params['name']) {
            throw new \Exception('Flex relations require a name');
        }

        if (!$params['class'] || !class_exists($params['class'])) {
            throw new \Exception('Flex relations require a class');
        }

        if (!$params['table']) {
            throw new \Exception('Flex relations require a table name');
        }

        return $this;
    }

    /**
     * Set the instance of a 'Belong' relation
     * 
     * @param mixed $instance
     * 
     * @return Relation
     */
    public function setBelongInstance($instance)
    {
        $this->loaded = true;
        $this->instance = $instance;
        return $this;
    }

    /**
     * Set the instance of a 'Has' relation
     * 
     * @param mixed $instance
     * 
     * @return Relation
     */
    public function setHasInstance($instance)
    {
        $this->loaded = true;
        $this->instance = $instance;
        return $this;
    }

    /**
     * Get the instance of this relation
     * 
     * @param Flex $model
     * 
     * @return array|object
     */
    public function getInstance(Flex $model)
    {
        if ($this->type === 'Belongs') {
            return $this->getBelongInstance($model);
        } elseif ($this->type === 'Has') {
            return $this->getHasInstance($model);
        } elseif ($this->type === 'HasAndBelongs') {
            return $this->getHasAndBelongsInstance($model);
        }
    }

    /**
     * Get the collection of related models
     * 
     * @param Flex $model
     * 
     * @return array
     */
    public function getHasAndBelongsInstance(Flex $model)
    {
        if (!$this->loaded) {
            $repo = FlexRepository::get();
            $query = "SELECT {$this->table}.* FROM {$this->table} JOIN {$this->relationTable} ON {$this->relationTable}.{$this->externalKey} = {$this->table}.id WHERE {$this->relationTable}.{$this->key} = :id";
            $models = $repo->query($query, [':id' => $model->id], ['table' => $this->table, 'class' => $this->class]);
            
            $this->instance = $models ? $models : [];
            $this->loaded = true;
        } else {
            if (!$this->instance) {
                $this->instance = [];
            }
        }

        return $this->instance;
    }

    /**
     * Get the collection of related models
     * 
     * @param Flex $model
     * 
     * @return array
     */
    public function getHasInstance(Flex $model)
    {
        if (!$this->loaded) {
            $repo = FlexRepository::get();
            $models = $repo->find(
                $this->table,
                "{$this->key} = :id",
                [':id' => $model->id],
                ['class' => $this->class
            ]);

            $this->instance = $models ? $models : [];
            $this->loaded = true;
        } else {
            if (!$this->instance) {
                $this->instance = [];
            }
        }

        return $this->instance;
    }

    /**
     * Get the instance of a 'Belong' relation
     * 
     * @param Flex $model
     * 
     * @return null|object
     */
    public function getBelongInstance(Flex $model)
    {
        if (!$this->loaded) {
            $repo = FlexRepository::get();
            $model = $repo->findOne(
                $this->table,
                'id = :id',
                [':id' => $model->{$this->key}],
                ['class' => $this->class
            ]);

            if ($model) {
                $this->loaded = true;
                $this->instance = $model;
            }
        }

        return $this->instance;
    }

    /**
     * Set the instance of a relation
     * 
     * @param mixed $instance
     * 
     * @return Relation
     */
    public function setInstance($instance)
    {
        if ($this->type === 'Belongs') {
            $this->instance = $instance;
        } else {
            if (!is_array($instance)) {
                $this->instance = [$instance];
            } else {
                $this->instance = $instance;
            }
        }

        $this->loaded = true;
        return $this;
    }

    /**
     * Add an instance to the collection 
     * or do nothing if the current instance is not a collection
     * 
     * @param mixed $model
     * 
     * @return Relation
     */
    public function add($model)
    {
        if ($this->isCollection()) {
            $this->instance[] = $model;
        } else {
            if (!$this->instance) {
                $this->instance = [];
                $this->instance[] = $model;
            }
        }

        return $this;
    }

    /**
     * Check if a relation's instance is empty
     * 
     * @return boolean
     */
    public function isEmpty()
    {
        return !$this->instance;
    }

    /**
     * Check if the instance is a collection
     * 
     * @return boolean
     */
    public function isCollection()
    {
        return is_array($this->instance);
    }

    /**
     * Check if the collection is empty
     * 
     * @return boolean
     */
    public function isEmptyCollection()
    {
        if (is_array($this->instance) && !$this->instance) {
            return true;
        }

        return false;
    }
}