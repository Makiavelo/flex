<?php

namespace Makiavelo\Flex;

/**
 * Class to handle collections of relations.
 */
class RelationManager
{
    /**
     * Collection of relations
     * @var array
     */
    public $relations = [];

    public function __construct()
    {

    }

    /**
     * Add a relation to the collection
     * The input parameters match the 'Relation' class
     * input parameters.
     * 
     * @param array $params
     * 
     * @return RelationManager
     */
    public function add($params)
    {
        if (!$this->has($params['name'])) {
            $relation = new Relation($params);
            $this->relations[$params['name']] = $relation;
        }

        return $this;
    }

    /**
     * Check if a relation exists
     * 
     * @param string $name
     * 
     * @return boolean
     */
    public function has($name)
    {
        if (isset($this->relations[$name])) {
            return true;
        }

        return false;
    }

    /**
     * Get a relation by name, or all of them if no name was provided
     * 
     * @param null|string $name
     * 
     * @return mixed
     */
    public function get($name = null)
    {
        if ($name === null) {
            return $this->relations;
        } else {
            if ($this->has($name)) {
                return $this->relations[$name];
            }
        }

        return false;
    }
}
