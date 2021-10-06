<?php

namespace Makiavelo\Flex\Traits;

/**
 * Simple trait to keep track of create and update dates.
 */
trait Timestampable
{
    public $created_at;
    public $updated_at;

    /**
     * Set the 'created_at' and 'updated_at' fields with the selected format.
     * 
     * @param $format
     * 
     * @return void
     */
    public function _timestampablePreSave($format = "Y-m-d H:i:s")
    {
        $date = date($format);
        if ($this->isNew()) {
            $this->created_at = $date;
        }
        $this->updated_at = $date;
    }
}