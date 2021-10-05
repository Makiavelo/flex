<?php

use Makiavelo\Flex\Flex;

class TagHook extends Flex
{
    public $id;
    public $name;
    
    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'tag');
        $this->relations()->add([
            'name' => 'Users',
            'key' => 'tag_id',
            'table' => 'user',
            'external_key' => 'user_id',
            'relation_table' => 'user_tag',
            'class' => 'UserHook',
            'type' => 'HasAndBelongs'
        ]);
    }
}

class UserHook extends Flex
{
    public $id;
    public $name;
    public $last_name;
    
    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
        $this->relations()->add([
            'name' => 'Tags',
            'table' => 'tag',
            'relation_table' => 'user_tag',
            'table_alias' => '',
            'class' => 'TagHook',
            'key' => 'user_id',
            'external_key' => 'tag_id',
            'type' => 'HasAndBelongs',
        ]);
    }

    public function valid()
    {
        if ($this->meta()->get('fail')) {
            $this->meta()->add('errors', ['Failure forced']);
            return false;
        }

        if ($this->meta()->get('scope') === 'new') {
            if (!$this->getLastName()) {
                $this->meta()->add('errors', ['Last name required']);
                return false;
            }
        }
        
        return true;
    }

    public function preSave()
    {
        return $this->valid();
    }

    public function postSave()
    {
        $this->meta()->add('action', 'postSave');
    }

    public function preDelete()
    {
        $scope = $this->meta()->get('scope');
        if ($scope === 'testDeleteFailure') {
            $this->meta()->add('errors', ['Testing delete errors']);
            return false;
        }

        return true;
    }

    public function postDelete()
    {
        $this->meta()->add('action', 'postDelete');
    }
}