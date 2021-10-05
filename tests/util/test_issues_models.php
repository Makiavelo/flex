<?php

use Makiavelo\Flex\Flex;

class TagIss1 extends Flex
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
            'class' => 'UserIss1',
            'type' => 'HasAndBelongs'
        ]);
    }
}

class UserIss1 extends Flex
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
            'class' => 'TagIss1',
            'key' => 'user_id',
            'external_key' => 'tag_id',
            'type' => 'HasAndBelongs',
        ]);
    }
}