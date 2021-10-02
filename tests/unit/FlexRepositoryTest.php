<?php

use PHPUnit\Framework\TestCase;

use \Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/test_models.php');

final class FlexRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown() : void {
        \Mockery::close();
    }

    public function testInstance()
    {
        $repo = FlexRepository::get();
        $this->assertEquals(get_class($repo), 'Makiavelo\\Flex\\FlexRepository');
    }

    public function testGetOperations()
    {
        $repo = FlexRepository::get();
        $models = [
            new Flex(),
            new Flex(),
            new Flex(),
            new Flex(),
            new Flex(),
        ];

        $models[1]->id = '123';
        $models[2]->id = '456';

        $ops = $repo->getOperations($models);

        $this->assertEquals(count($ops['inserts']), 3);
        $this->assertEquals(count($ops['updates']), 2);
    }

    public function testPreSavesTriggers()
    {
        $repo = FlexRepository::get();
        $spy = m::spy(Flex::class);
        
        $elems = [$spy, $spy, $spy];
        $repo->preSaves($elems);

        $spy->shouldHaveReceived('preSave')
            ->times(3);
    }

    public function testPostSavesTriggers()
    {
        $repo = FlexRepository::get();
        $spy = m::spy(Flex::class);
        
        $elems = [$spy, $spy, $spy];
        $repo->postSaves($elems);

        $spy->shouldHaveReceived('postSave')
            ->times(3);
    }

    public function testFieldsAndValues()
    {
        $repo = FlexRepository::get();

        $model = new Flex();
        $model->id = '123';
        $model->name = 'John';
        $model->last_name = 'Doe';
        $model->phone = '5678';

        $result = $repo->getFieldsAndValues($model);

        $this->assertCount(2, $result);
        $this->assertCount(3, $result['fields']);
        $this->assertCount(3, $result['values']);

        $this->assertEquals($result['fields'][0], 'name');
        $this->assertEquals($result['fields'][1], 'last_name');
        $this->assertEquals($result['fields'][2], 'phone');

        $this->assertEquals($result['values'][0], 'John');
        $this->assertEquals($result['values'][1], 'Doe');
        $this->assertEquals($result['values'][2], '5678');
    }

    public function testGetCollectionIds()
    {
        $repo = FlexRepository::get();

        $model1 = new Flex();
        $model1->id = '123';

        $model2 = new Flex();
        $model2->id = '456';

        $ids = $repo->getCollectionIds([$model1, $model2]);

        $this->assertCount(2, $ids);
        $this->assertEquals($ids[0], '123');
        $this->assertEquals($ids[1], '456');
    }

    public function testCreate()
    {
        $repo = FlexRepository::get();
        $model = $repo->create('user');

        $this->assertEquals($model->meta()->get('table'), 'user');
        $this->assertEquals(get_class($model), 'Makiavelo\\Flex\\Flex');
    }

    public function testAddModelFields()
    {
        $repo = FlexRepository::get();
        $model = $repo->create('user');

        $model->meta()->add('fields', [
            'name' => ['type' => 'VARCHAR(150)', 'nullable' => true],
            'description' => ['type' => 'TEXT', 'nullable' => true],
        ]);

        $fields = $repo->addModelFields($model, []);

        $this->assertCount(2, $fields);
        
        $this->assertEquals($fields[0]['name'], 'name');
        $this->assertEquals($fields[0]['type'], 'VARCHAR(150)');
        $this->assertTrue($fields[0]['null']);
        
        $this->assertEquals($fields[1]['name'], 'description');
        $this->assertEquals($fields[1]['type'], 'TEXT');
        $this->assertTrue($fields[1]['null']);
    }

    public function testAddNewFields()
    {
        $repo = FlexRepository::get();
        $model = $repo->create('user');
        $model->current_field = '123';
        $model->other_current_field = 'abcd';
        $model->new_field = '456';
        $model->other_new_field = '789';

        $tableFields = [
            ['Field' => 'current_field']
        ];

        $fields = [
            ['name' => 'other_current_field']
        ];

        $newFields = $repo->addNewFields($model, $tableFields, $fields);

        // 'other_current_field' is kept because $fields holds
        // the parameters that are going to be created.
        // This method adds to the $fields variable the ones that are not
        // already there or not in the database.
        $this->assertCount(3, $newFields);
        $this->assertEquals('other_current_field', $newFields[0]['name']);
        $this->assertEquals('new_field', $newFields[1]['name']);
        $this->assertEquals('other_new_field', $newFields[2]['name']);
    }
}
