<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Util\Common;

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

    // Magic methods for getters && setters
    // Can be overriden
    public function __call($name, $arguments)
    {
        // Check if it's a getter/setter
        $getSetPreffix = substr($name, 0, 3);
        if (in_array($getSetPreffix, ['get', 'set'])) {
            $camelCaseAttr = substr($name, 3);
            $snakeCaseAttr = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCaseAttr));
            if (property_exists($this, $snakeCaseAttr)) {
                if ($getSetPreffix === 'get') {
                    return $this->$snakeCaseAttr;
                } else {
                    $this->$snakeCaseAttr = $arguments[0];
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