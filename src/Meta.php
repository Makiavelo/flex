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
     * Remove the selected path element from the meta values
     * 
     * @param string $path
     * 
     * @return Meta
     */
    public function remove($path) {
        Common::remove($this->values, $path);
        return $this;
    }

    /**
     * Get a meta value
     * 
     * @param string $name
     * @param mixed $default
     * 
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return Common::get($this->values, $name, $default);
    }

    /**
     * Set a value in a determined path.
     * 
     * If the path doesn't exist, the missing parts are created.
     * 
     * @param mixed $path
     * @param mixed $value
     * @param string $container
     * 
     * @return Meta
     */
    public function set($path, $value, $container = 'array')
    {
        $this->values = Common::set($this->values, $path, $value, $container);
        return $this;
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
