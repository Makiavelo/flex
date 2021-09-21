<?php

namespace Makiavelo\Flex;

use Makiavelo\Flex\Util\Common;

class Flex
{
    // Internal variable for meta data like table name
    public $_meta;

    public function __construct()
    {

    }

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

    // Convert to json by default
    public function __toString()
    {
        return json_encode($this->getAttributes());
    }

    // Get object attributes ignoring meta && ids
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

    // Get the metta associative array
    public function _meta()
    {
        return $this->_meta;
    }

    // Determine if it's an internal attribute
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

    // Add meta values
    public function addMeta($name, $value = null)
    {
        $this->_meta[$name] = $value;
    }

    // Get meta values
    public function getMeta($path, $default = null)
    {
        return Common::get($this->_meta, $path, $default);
    }

    // Override for validation
    public function valid()
    {
        return true;
    }

    // Hooks
    // Override for custom behaviors
    public function preSave()
    {
        return true;
    }

    public function postSave()
    {
        return true;
    }

    public function preDelete()
    {
        return true;
    }

    public function postDelete()
    {
        return true;
    }
}