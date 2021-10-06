<?php

namespace Makiavelo\Flex\Traits;

/**
 * Trait to add slugs based on certain fields
 * The fields will be concatenated and then transformed into a slug
 */
trait Sluggable
{
    public $slug;

    /**
     * Initialize the required parameters
     *   'fields': The fields to be concatenated
     *   'update': If the slug should be updated once created
     * 
     * @param mixed $params
     * 
     * @return void
     */
    public function _sluggableInit($params)
    {
        $this->meta()->set('protected->traits->sluggable', $params);
    }

    /**
     * Build a slug based on the selected fields
     * 
     * @return void
     */
    public function _sluggablePreSave()
    {
        $params = $this->meta()->get('protected->traits->sluggable');
        $update = $params['update'];

        if ($update || !$this->slug) {
            $fields = $params['fields'];
            $string = '';
            foreach ($fields as $key => $field) {
                $string .= ($key === 0 ? '' : ' ');
                $string .= $this->$field;
            }

            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
            $this->slug = $slug;
        }
    }
}