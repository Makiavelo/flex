<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Flex;

class Chainer
{
    /**
     * Adds a relation to the chain, this is triggered when a model's
     * relation magic method is called.
     * Example: $company->users()
     * 'users' is the magic method, which is just the relation name with
     * the first letter with low case.
     * 
     * @param Flex $model
     * @param mixed $name
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function addRelation(Flex $model, $name, $arguments)
    {
        $model->meta()->add(
            'chain',
            ['relation' => [
                $model->relations()->get($name, true),
                $arguments
            ]
        ]);

        return $model;
    }

    /**
     * Handle all the available chained methods.
     * 
     * @param Flex $model
     * @param mixed $name
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function handleMethods(Flex $model, $name, $arguments)
    {
        $chainMethods = ['not', 'with', 'add', 'remove', 'clear', 'exists', 'fetch'];
        if (in_array($name, $chainMethods)) {
            if (in_array($name, ['with', 'not'])) {
                // Chain methods that are just stored in the chain
                return $this->handleConditions($name, $model, $arguments);
            } else {
                // There's a method defined for each chain method name
                return call_user_func([$this, $name], $model, $arguments);
            }
        }

        return false;
    }

    /**
     * Magic method to handle operations that don't interact
     * directly with the collection, they just store information
     * in the chain for later filtering or manipulation.
     * 
     * @param mixed $name
     * @param mixed $model
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function handleConditions($name, $model, $arguments)
    {
        $chain = $model->meta()->get('chain');
        $chain[$name] = $arguments ? $arguments[0] : $name;
        $model->meta()->add('chain', $chain);
        return $model;
    }

    /**
     * Magic method to remove elements from the collection.
     * 
     * @param mixed $model
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function remove($model, $arguments)
    {
        $relation = $model->meta()->get('chain->relation->0');
        $setter = 'set' . $relation->name;

        $result = $this->filterCollection($model);
        $filtered =  $this->getResult(!$model->meta()->get('chain->not'), $result);
        $model->{$setter}($filtered);
        return $model;
    }

    /**
     * Magic method to check if a condition exists in the collection
     * 
     * @param mixed $model
     * @param mixed $arguments
     * 
     * @return boolean
     */
    public function exists($model, $arguments)
    {
        $result = $this->filterCollection($model);
        $filtered =  $this->getResult($model->meta()->get('chain->not'), $result);
        if ($filtered) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Magic method to add an element to the collection
     * 
     * @param mixed $model
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function add($model, $arguments)
    {
        $relation = $model->meta()->get('chain->relation->0');
        $getter = 'get' . $relation->name;
        $setter = 'set' . $relation->name;

        $current = $model->{$getter}();
        $current[] = $arguments[0];
        $model->{$setter}($current);
        return $model;
    }

    /**
     * Magic method to fetch a filtered collection (or all of it)
     * 
     * @param mixed $model
     * @param mixed $arguments
     * 
     * @return array
     */
    public function fetch($model, $arguments)
    {
        $result = $this->filterCollection($model);
        return $this->getResult($model->meta()->get('chain->not'), $result);
    }

    /**
     * Magic method to clear a collection
     * 
     * @param mixed $model
     * @param mixed $arguments
     * 
     * @return mixed
     */
    public function clear($model, $arguments)
    {
        $relation = $model->meta()->get('chain->relation->0');
        $setter = 'set' . $relation->name;

        $model->{$setter}([]);
        return $model;
    }

    /**
     * Internal method to make it easier to get results
     * 
     * @param mixed $not
     * @param mixed $collection
     * 
     * @return array
     */
    public function getResult($not, $collection)
    {
        $result = $not ? $collection['without'] : $collection['with'];
        return $result;
    }

    /**
     * Filter a collection based on the conditions previously set via 'with'
     * The condition can be:
     * 1) array: all the attributes will be compared against each collection element.
     * 2) callable: if the callable returns true, then it qualifies.
     * 3) Flex model: In this case, only ids are compared.
     * 
     * @param Flex $parent
     */
    public function filterCollection(Flex $parent)
    {
        $relation = $parent->meta()->get('chain->relation->0');
        $getter = 'get' . $relation->name;
        $coll = $parent->{$getter}();
        $condition = $parent->meta()->get('chain->with');
        $newColl = ['with' => [], 'without' => []];
        if ($coll) {
            foreach ($coll as $model) {
                if (!$condition) {
                    $newColl['with'] = $coll;
                } elseif (is_callable($condition)) {
                    $newColl = $this->handleCallable($model, $condition, $newColl);
                } elseif (is_array($condition)) {
                    $newColl = $this->handleArray($model, $relation, $condition, $newColl);
                } elseif (is_a($condition, 'Makiavelo\\Flex\\Flex')) {
                    $newColl = $this->handleFlex($model, $relation, $condition, $newColl);
                }
            }
        }

        return $newColl;
    }

    /**
     * Handle conditions in Callable format
     * This method requires a function that returns a boolean.
     * If it returns true, then it's considered as a match.
     * 
     * @param mixed $model
     * @param mixed $condition
     * @param mixed $collection
     * 
     * @return array
     */
    public function handleCallable($model, $condition, $collection)
    {
        $status = call_user_func($condition, $model);
        if ($status) {
            $collection['with'][] = $model;
        } else {
            $collection['without'] = $model;
        }

        return $collection;
    }

    /**
     * Handle conditions in array format.
     * This method checks every property set in the array
     * against each of the collection's models.
     * 
     * @param mixed $model
     * @param mixed $relation
     * @param mixed $condition
     * @param mixed $collection
     * 
     * @return array
     */
    public function handleArray($model, $relation, $condition, $collection)
    {
        if (!$relation->isEmpty()) {
            foreach ($condition as $key => $value) {
                if ($model->$key === $value) {
                    $collection['with'][] = $model;
                } else {
                    $collection['without'][] = $model;
                }
            }
        }

        return $collection;
    }

    /**
     * Handles conditions in the Flex Model format
     * This method compares model ids only.
     * 
     * @param mixed $model
     * @param mixed $relation
     * @param mixed $condition
     * @param mixed $collection
     * 
     * @return array
     */
    public function handleFlex($model, $relation, $condition, $collection)
    {
        if (!$relation->isEmpty()) {
            if ($model->id === $condition->id) {
                $collection['with'][] = $model;
            } else {
                $collection['without'][] = $model;
            }
        }

        return $collection;
    }
}