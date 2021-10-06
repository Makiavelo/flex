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
     * @param boolean $noCase
     * 
     * @return boolean
     */
    public function has($name, $noCase = false)
    {
        if (isset($this->relations[$name])) {
            return true;
        } elseif ($noCase && isset($this->relations[ucfirst($name)])) {
            return true;
        }

        return false;
    }

    /**
     * Get a relation by name, or all of them if no name was provided
     * 
     * @param null|string $name
     * @param boolean $noCase
     * 
     * @return mixed
     */
    public function get($name = null, $noCase = false)
    {
        if ($name === null) {
            return $this->relations;
        } else {
            if ($this->has($name)) {
                return $this->relations[$name];
            } elseif ($noCase && $this->has($name, $noCase)) {
                return $this->relations[ucfirst($name)];
            }
        }

        return false;
    }

    /**
     * Edit a relation 
     * 
     * Changes relation attributes based on the values provided
     * in the $params associative array.
     * 
     * @param mixed $name
     * @param array $params
     * 
     * @return RelationManager
     */
    public function edit($name, $params = [])
    {
        $relation = $this->get($name);
        foreach ($params as $key => $value) {
            $relation->$key = $value;
        }
        return $this;
    }
}
