<?php

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\Traits\Timestampable;
use Makiavelo\Flex\Traits\Sluggable;
use Makiavelo\Flex\Traits\Geopositioned;
use Makiavelo\Flex\Traits\Translatable;
use Makiavelo\Flex\Traits\Versionable;

class TraitTestTimestampable extends Flex
{
    use Timestampable;

    public $id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'test_timestampable');
    }

    public function preSave()
    {
        $this->_timestampablePreSave();
        return true;
    }
}

class TraitTestSluggable extends Flex
{
    use Sluggable;

    public $id;
    public $name;
    public $last_name;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'test_sluggable');
        $this->_sluggableInit([
            'fields' => ['name', 'last_name'],
            'update' => true
        ]);
    }

    public function preSave()
    {
        $this->_sluggablePreSave();
        return true;
    }
}

class TraitTestGeopositioned extends Flex
{
    use Geopositioned;

    public $id;
    public $name;
    public $last_name;
    public $distance_to_rome;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'test_geo');
    }

    public function preSave()
    {
        $lat = '41.9097306';
        $lng = '12.2558141';
        $distance = $this->distanceTo($lat, $lng);
        $this->setDistanceToRome($distance);

        return true;
    }
}

class TraitTestTranslatable extends Flex
{
    use Translatable;

    public $id;
    public $name;
    public $last_name;
    public $description;
    public $short_description;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'test_translatable');
        $this->_translatableInit();
    }
}

class TraitTestVersionable extends Flex
{
    use Versionable;

    public $id;
    public $name;
    public $last_name;
    public $description;

    public function __construct()
    {
        parent::__construct();
        $this->meta()->add('table', 'test_versionable');
        $this->_translatableInit();
    }

    public function preSave()
    {
        $this->_versionablePreSave();
        return true;
    }
}