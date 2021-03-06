<?php

namespace Makiavelo\Flex\Util;

class Common
{
    /**
     * Get a value from a collection using a path.
     * Eg: Common::get($collection, 'user->card->number)
     *     will look for a number, inside the card, inside the user.
     *     if the path doesn't exist, a default value will be returned.
     * 
     * @param array $collection
     * @param null $key
     * @param null $default
     * 
     * @return mixed
     */
    public static function get($collection = [], $key = null, $default = null)
    {
        if ($key === null || $key === false) {
            return $collection;
        }

        if (!is_object($collection) && isset($collection[$key])) {
            return $collection[$key];
        }

        foreach (explode('->', $key) as $segment) {
            if (is_object($collection)) {
                if (!isset($collection->{$segment})) {
                    return $default;
                } else {
                    $collection = $collection->{$segment};
                }
            } else {
                if (!isset($collection[$segment])) {
                    return $default;
                } else {
                    $collection = $collection[$segment];
                }
            }
        }

        return $collection;
    }

    /**
     * Remove an element by key from the collection
     * 
     * If something was unset, returns true, false otherwise.
     * 
     * @param mixed $collection
     * @param string $key
     * 
     * @return boolean
     */
    public static function remove(&$collection, $key)
    {
        if ($key === null || $key === false) {
            return false;
        }

        $parts = explode('->', $key);
        $total = count($parts);

        if ($total === 1) {
            if (isset($collection[$parts[0]])) {
                unset($collection[$parts[0]]);
                return true;
            } else {
                return false;
            }
        } else {
            if (!isset($collection[$parts[0]])) {
                return true;
            }

            $current = array_shift($parts);
            self::remove($collection[$current], implode('->', $parts));
        }
    }

    /**
     * Set a value inside a collection using a path.
     * Eg: Common::set($collection, 'user->card->number)
     *     if collection doesn't have the 'user' attribute
     *     this will create an array, with the card array
     *     and the number value inside that array.
     * 
     * @param mixed $collection
     * @param mixed $key
     * @param null $value
     * @param string $container
     * 
     * @return mixed
     */
    public static function set($collection, $key, $value = null, $container = 'array')
    {
        $parts = explode('->', $key);
        $count = count($parts);
        $segment = array_shift($parts);

        if ($count === 1) {
            if (is_object($collection)) {
                $collection->{$segment} = $value;
            } else {
                $collection[$segment] = $value;
            }
        } else {
            if (is_object($collection)) {
                if (!isset($collection->{$segment}) || !$collection->{$segment}) {
                    $collection->{$segment} = $container === 'array' ? [] : new \stdClass();
                }
                $collection->{$segment} = Common::set($collection->{$segment}, implode('->', $parts), $value, $container);
            } else {
                if (!isset($collection[$segment]) || !$collection[$segment]) {
                    $collection[$segment] = $container === 'array' ? [] : new \stdClass();
                }
                $collection[$segment] = Common::set($collection[$segment], implode('->', $parts), $value, $container);
            }
        }
        
        return $collection;
    }

    /**
     * Find one or more elements inside a collection where
     * the path resolves to the value provided.
     * 
     * Eg: Common::find([['name' => 'john'], ['name' => 'joe']], 'name', 'john')
     *          -> resolves to ['name' => 'john']
     * 
     * Multiple Example:
     *      $collection = [
     *          ['name' => 'john'],
     *          ['name' => 'john'],
     *          ['name' => 'joe']
     *      ];
     * 
     *      Common::find($collection, 'name', 'john')
     *      -> Resolves to:
     *          [
     *              ['name' => 'john'],
     *              ['name' => 'john']
     *          ]
     * 
     * @param mixed $collection
     * @param string $key
     * @param mixed $value
     * @param bool $multiple
     * 
     * @return mixed
     */
    public static function find($collection, $key, $value, $multiple = false)
    {
        $return = $multiple ? [] : null;

        if ($collection) {
            foreach ($collection as $elem) {
                $match = false;
                $elemValue = Common::get($elem, $key);
                if (is_array($value) && in_array($value, $elemValue)) {
                    $match = true;
                } elseif ($value === $elemValue) {
                    $match = true;
                }

                if ($match) {
                    if ($multiple) {
                        $return[] = $elem;
                    } else {
                        return $elem;
                    }
                }
            }
        }

        return $return;
    }

    public static function isCollection($var)
    {
        $is = false;
        // Can be associative
        if (is_array($var)) {
            if ($var) {
                // Are all numeric keys?
                $is = array_keys($var) === range(0, count($var) - 1);
            }
        }

        return $is;
    }
}