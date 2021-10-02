<?php

use Makiavelo\Flex\Flex;

class Stuff extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'stuff');
    }
}

class OtherStuff extends Flex {
    public $id;
    public $name;
}

class Company extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'company');
        $this->relations()->add([
            'name' => 'Users',
            'table' => 'user',
            'class' => 'User',
            'key' => 'company_id',
            'type' => 'Has',
        ]);
    }
}

class User extends Flex {
    public $id;
    public $company_id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'user');
        $this->relations()->add([
            'name' => 'Company',
            'table' => 'company',
            'class' => 'Company',
            'key' => 'company_id',
            'type' => 'Belongs',
        ]);
    }

    public function getCompanyName()
    {
        return $this->getCompany()->getName();
    }
}

class Modelx extends Flex {
    public $id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'modelx');
    }
}

class CompanyX extends Flex {
    public $id;
    public $name;
    public $owner_id;
    public $manager_id;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'companyx');
        $this->relations()->add([
            'name' => 'Owner',
            'table' => 'userx',
            'table_alias' => 'owner',
            'class' => 'UserX',
            'key' => 'owner_id',
            'type' => 'Belongs',
        ]);

        $this->relations()->add([
            'name' => 'Manager',
            'table' => 'userx',
            'table_alias' => 'manager',
            'class' => 'UserX',
            'key' => 'manager_id',
            'type' => 'Belongs',
        ]);
    }
}

class UserX extends Flex {
    public $id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'userx');
    }
}

class UserY extends Flex {
    public $id;
    public $parent_id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'usery');
        $this->relations()->add([
            'name' => 'Parent',
            'table' => 'usery',
            'table_alias' => 'parent',
            'class' => 'UserY',
            'key' => 'parent_id',
            'type' => 'Belongs',
        ]);
    }
}

class UserA extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'usera');
        $this->relations()->add([
            'name' => 'UserTags',
            'table' => 'usera_tag',
            'table_alias' => '',
            'class' => 'UserTag',
            'key' => 'usera_id',
            'type' => 'Has',
        ]);
    }
}

class Tag extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'tag');
        $this->relations()->add([
            'name' => 'UserTags',
            'table' => 'usera_tag',
            'table_alias' => '',
            'class' => 'UserTag',
            'key' => 'tag_id',
            'type' => 'Has',
        ]);
    }
}

class UserTag extends Flex {
    public $id;
    public $user_id;
    public $tag_id;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'usera_tag');
        $this->relations()->add([
            'name' => 'User',
            'table' => 'usera',
            'table_alias' => '',
            'class' => 'UserA',
            'key' => 'user_id',
            'type' => 'Belongs',
        ]);

        $this->relations()->add([
            'name' => 'Tag',
            'table' => 'tag',
            'table_alias' => '',
            'class' => 'Tag',
            'key' => 'tag_id',
            'type' => 'Belongs',
        ]);
    }
}

class UserB extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'userb');
        $this->relations()->add([
            'name' => 'Tags',
            'table' => 'tagb',
            'relation_table' => 'userb_tagb',
            'table_alias' => '',
            'class' => 'TagB',
            'key' => 'user_id',
            'external_key' => 'tag_id',
            'type' => 'HasAndBelongs',
        ]);
    }
}

class TagB extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'tagb');
        $this->relations()->add([
            'name' => 'Users',
            'table' => 'userb',
            'relation_table' => 'userb_tagb',
            'table_alias' => '',
            'class' => 'UserB',
            'key' => 'tag_id',
            'external_key' => 'user_id',
            'type' => 'HasAndBelongs',
        ]);
    }
}

Class CompanyB extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'companyb');
        $this->relations()->add([
            'name' => 'Users',
            'table' => 'userc',
            'class' => 'UserC',
            'key' => 'company_id',
            'type' => 'Has',
        ]);
    }
}

Class UserC extends Flex
{
    public $id;
    public $company_id;
    public $name;
    
    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'userc');
        $this->relations()->add([
            'name' => 'Company',
            'table' => 'companyb',
            'class' => 'CompanyB',
            'key' => 'company_id',
            'type' => 'Belongs',
        ]);
    }
}

class UserD extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'userd');
        $this->relations()->add([
            'name' => 'Tags',
            'table' => 'tagd',
            'relation_table' => 'userb_tagd',
            'table_alias' => '',
            'class' => 'TagD',
            'key' => 'user_id',
            'external_key' => 'tag_id',
            'type' => 'HasAndBelongs',
        ]);
    }
}

class TagD extends Flex
{
    public $id;
    public $name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'tagd');
        $this->relations()->add([
            'name' => 'Users',
            'table' => 'userd',
            'relation_table' => 'userd_tagd',
            'table_alias' => '',
            'class' => 'UserD',
            'key' => 'tag_id',
            'external_key' => 'user_id',
            'type' => 'HasAndBelongs',
        ]);
    }
}