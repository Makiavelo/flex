<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Util\Common;

/**
 * Class to handle meta data of a class
 * Usage examples: table name, table fields definition, etc.
 */
class Meta
{
    /**
     * Container for all the meta values
     * @var array
     */
    public $values;

    public function __construct()
    {

    }

    /**
     * Add a key/value pair
     * 
     * @param mixed $key
     * @param mixed $value
     * 
     * @return Meta
     */
    public function add($key, $value)
    {
        $this->values[$key] = $value;
        return $this;
    }

    /**
     * Get a meta value
     * 
     * @param string $name
     * 
     * @return mixed
     */
    public function get($name)
    {
        return Common::get($this->values, $name);
    }

    /**
     * Check if the key exists in the meta data array
     * 
     * @param string $name
     * 
     * @return boolean
     */
    public function has($name)
    {
        return !!$this->get($name);
    }

    /**
     * Utility method to get the actual table name
     * even if it's aliased. Used to perform queries.
     * 
     * @param string $alias
     * 
     * @return string
     */
    public function getAliasOrTable($alias)
    {
        if ($alias) {
            return $alias;
        } elseif ($this->get('table')) {
            return $this->get('table');
        }

        return '';
    }
}
