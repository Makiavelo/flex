<?php

use PHPUnit\Framework\TestCase;

use \Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Makiavelo\Flex\Flex;

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
}
