<?php

use Makiavelo\Flex\Flex;

class Tag extends Flex
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
            'class' => 'User',
            'type' => 'HasAndBelongs'
        ]);
    }
}

class User extends Flex
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
            'class' => 'Tag',
            'key' => 'user_id',
            'external_key' => 'tag_id',
            'type' => 'HasAndBelongs',
        ]);
    }
}