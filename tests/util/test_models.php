<?php

use Makiavelo\Flex\Flex;

class Stuff extends Flex {
    public $id;
    public $name;

    public function __construct()
    {
        $this->addMeta('table', 'stuff');
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
        $this->addMeta('table', 'company');
        $this->addRelation([
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
        $this->addMeta('table', 'user');
        $this->addRelation([
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
        $this->addMeta('table', 'modelx');
    }
}

class CompanyX extends Flex {
    public $id;
    public $name;
    public $owner_id;
    public $manager_id;

    public function __construct()
    {
        $this->addMeta('table', 'companyx');
        $this->addRelation([
            'name' => 'Owner',
            'table' => 'userx',
            'table_alias' => 'owner',
            'class' => 'UserX',
            'key' => 'owner_id',
            'type' => 'Belongs',
        ]);

        $this->addRelation([
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
        $this->addMeta('table', 'userx');
    }
}

class UserY extends Flex {
    public $id;
    public $parent_id;
    public $name;
    public $last_name;

    public function __construct()
    {
        $this->addMeta('table', 'usery');
        $this->addRelation([
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
        $this->addMeta('table', 'usera');
        $this->addRelation([
            'name' => 'UserTags',
            'table' => 'user_tag',
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
        $this->addMeta('table', 'tag');
        $this->addRelation([
            'name' => 'UserTags',
            'table' => 'user_tag',
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
        $this->addMeta('table', 'usera_tag');
        $this->addRelation([
            'name' => 'User',
            'table' => 'usera',
            'table_alias' => '',
            'class' => 'UserA',
            'key' => 'user_id',
            'type' => 'Belongs',
        ]);

        $this->addRelation([
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
        $this->addMeta('table', 'userb');
        $this->addRelation([
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
        $this->addMeta('table', 'tagb');
        $this->addRelation([
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