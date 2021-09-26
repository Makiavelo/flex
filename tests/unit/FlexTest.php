<?php

use PHPUnit\Framework\TestCase;

use \Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Makiavelo\Flex\Flex;

class Stuff extends Flex {
    public $id;
    public $name;
}

final class FlexTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown() : void {
        \Mockery::close();
    }

    public function testIsNew()
    {
        $model = new Flex();
        $this->assertTrue($model->isNew());

        $model->id = '123';
        $this->assertFalse($model->isNew());
    }

    public function testMagicGettersAndSetters()
    {
        $model = new Flex();
        $model->name = 'John Doe';
        $this->assertEquals($model->getName(), 'John Doe');

        $model->setName('Mark Doe');
        $this->assertEquals($model->name, 'Mark Doe');
    }

    public function testSettersException()
    {
        $this->expectException(\Exception::class);
        $model = new Flex();
        $model->setName('John Doe');
    }

    public function testGettersException()
    {
        $this->expectException(\Exception::class);
        $model = new Flex();
        $model->getName();
    }

    public function testStringify()
    {
        $model = new Flex();
        $model->name = 'test';
        $str = $model->__toString();

        $this->assertEquals($str, '{"name":"test"}');

        $decoded = json_decode($str);
        $this->assertEquals($decoded->name, 'test');
    }

    public function testGetAttributes()
    {
        $model = new Flex();
        $model->id = '123';
        $model->name = 'tester';
        $model->age = 50;
        $model->addMeta('table', 'user');

        $attrs = $model->getAttributes();
        $this->assertEquals(count($attrs), 2);

        $this->assertEquals($attrs['name'], 'tester');
        $this->assertEquals($attrs['age'], 50);
    }

    public function testGetMeta()
    {
        $model = new Flex();
        $model->addMeta('table', 'test_table');

        $meta = $model->_meta();
        $this->assertEquals($meta['table'], 'test_table');
    }

    public function testIsInternal()
    {
        $model = new Flex();

        $this->assertTrue($model->isInternal('_meta'));
        $this->assertTrue($model->isInternal('_other_stuff'));
        $this->assertTrue($model->isInternal('id'));
        $this->assertFalse($model->isInternal('name'));
        $this->assertFalse($model->isInternal('phone_'));
    }

    public function testHydrateFlex()
    {
        $data = [
            'name' => 'John',
            'last_name' => 'Doe'
        ];

        $model = new Flex();
        $model->hydrate($data);

        $this->assertEquals($model->getName(), 'John');
        $this->assertEquals($model->getLastName(), 'Doe');
    }

    public function testHydrateCustom()
    {
        $data = [
            'name' => 'John',
            'last_name' => 'Doe'
        ];

        $model = new Stuff();
        $model->hydrate($data);

        $this->assertEquals($model->getName(), 'John');
        $this->assertFalse(isset($model->last_name));
    }

    public function testBuild()
    {
        $data = ['name' => 'John'];
        $stuff = Stuff::build($data);

        $this->assertEquals(get_class($stuff), 'Stuff');
        $this->assertEquals($stuff->getName(), 'John');
    }

    public function testBuildCollection()
    {
        $data = [
            ['name' => 'John'],
            ['name' => 'Jack'],
            ['name' => 'Will']
        ];

        $modelCollection = array_map(['Stuff', 'build'], $data);

        $this->assertEquals('John', $modelCollection[0]->getName());
        $this->assertEquals('Jack', $modelCollection[1]->getName());
        $this->assertEquals('Will', $modelCollection[2]->getName());
    }

    public function testBuildFlexCollection()
    {
        $data = [
            ['name' => 'John'],
            ['name' => 'Jack'],
            ['name' => 'Will']
        ];

        $modelCollection = array_map(['Makiavelo\\Flex\\Flex', 'build'], $data);

        $this->assertEquals('John', $modelCollection[0]->getName());
        $this->assertEquals('Jack', $modelCollection[1]->getName());
        $this->assertEquals('Will', $modelCollection[2]->getName());
    }
}
