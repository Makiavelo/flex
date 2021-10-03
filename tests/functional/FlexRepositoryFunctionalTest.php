<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;
use Makiavelo\Flex\Util\EnhancedPDO;

require_once(dirname(__FILE__) . '/../util/test_models.php');

final class FlexRepositoryFunctionalTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $db = new \PDO($_ENV['PDO_DSN'], $_ENV['PDO_USER'], $_ENV['PDO_PASS']);
        $sql = file_get_contents(dirname(__FILE__) . '/fixtures/flex_repo_setup_before.sql');
        $qr = $db->exec($sql);
    }

    protected function setUp(): void
    {
        
    }

    protected function tearDown(): void
    {
        
    }

    public static function tearDownAfterClass(): void
    {
        $db = new \PDO($_ENV['PDO_DSN'], $_ENV['PDO_USER'], $_ENV['PDO_PASS']);
        $sql = file_get_contents(dirname(__FILE__) . '/fixtures/flex_tear_down_after.sql');
        $qr = $db->exec($sql);
    }

    public function testConnect()
    {
        $repo = FlexRepository::get();
        $status = $repo->connect([
            'host' => '172.17.0.1',
            'db' => 'flex_test',
            'user' => 'root',
            'pass' => 'root'
        ]);

        $this->assertTrue($status);
        $this->assertEquals(get_class($repo->db), 'Makiavelo\\Flex\\Drivers\\PDOMySQL');
    }

    /**
     * @depends testConnect
     */
    public function testSave()
    {
        $repo = FlexRepository::get();
        $model = $repo->create('test_save');
        $model->name = 'John';
        $model->last_name = 'Doe';

        $repo->save($model);

        $fields = $repo->db->getTableFields('test_save');
        $this->assertCount(3, $fields);

        $this->assertEquals($fields[0]['Field'], 'id');
        $this->assertEquals($fields[0]['Type'], 'int');
        $this->assertEquals($fields[0]['Key'], 'PRI');
        $this->assertEquals($fields[0]['Extra'], 'auto_increment');

        $this->assertEquals($fields[1]['Field'], 'name');
        $this->assertEquals($fields[1]['Type'], 'text');

        $this->assertEquals($fields[2]['Field'], 'last_name');
        $this->assertEquals($fields[2]['Type'], 'text');

        $this->assertNotNull($model->id);
        $model->name = 'Peter';
        $repo->save($model);

        $result = $repo->find('test_save', "id = '{$model->id}'");
        $this->assertEquals($result[0]->name, 'Peter');
    }

    public function testDelete()
    {
        $repo = FlexRepository::get();
        $model = $repo->create('test_delete');
        $model->name = 'John';
        $model->last_name = 'Doe';

        $repo->save($model);
        $result = $repo->find('test_delete', "id = '{$model->id}'");
        $this->assertEquals($result[0]->name, 'John');

        $repo->delete($model);

        $result = $repo->find('test_delete', "id = '{$model->id}'");
        $this->assertCount(0, $result);
    }

    public function testSaveCollection()
    {
        $repo = FlexRepository::get();

        $model1 = $repo->create('test_save_collection');
        $model1->name = 'John';
        $model1->last_name = 'Doe';

        $model2 = $repo->create('test_save_collection');
        $model2->name = 'Jack';
        $model2->last_name = 'Daniels';

        $model3 = $repo->create('test_save_collection');
        $model3->name = 'Will';
        $model3->last_name = 'Ferrel';

        $status = $repo->saveCollection([$model1, $model2, $model3]);
        $this->assertTrue($status);

        $result = $repo->find('test_save_collection', "id = '{$model1->id}'");
        $this->assertEquals($result[0]->name, 'John');

        $result = $repo->find('test_save_collection', "id = '{$model2->id}'");
        $this->assertEquals($result[0]->name, 'Jack');

        $result = $repo->find('test_save_collection', "id = '{$model3->id}'");
        $this->assertEquals($result[0]->name, 'Will');
    }

    public function testDeleteCollection()
    {
        $repo = FlexRepository::get();

        $model1 = $repo->create('test_delete_collection');
        $model1->name = 'John';
        $model1->last_name = 'Doe';

        $model2 = $repo->create('test_delete_collection');
        $model2->name = 'Jack';
        $model2->last_name = 'Daniels';

        $model3 = $repo->create('test_delete_collection');
        $model3->name = 'Will';
        $model3->last_name = 'Ferrel';

        $repo->saveCollection([$model1, $model2, $model3]);

        $repo->db->deleteCollection([$model1, $model2, $model3]);

        $result = $repo->find('test_delete_collection', "id IN ('{$model1->id}', '{$model2->id}', '{$model3->id}')");
        $this->assertCount(0, $result);
    }

    public function testUpdateMixedCollection()
    {
        $repo = FlexRepository::get();

        $preexistent = $repo->create('test_update_mixed_collection');
        $preexistent->name = 'John';
        $preexistent->last_name = 'Doe';
        $repo->save($preexistent);

        $preexistent->name = 'Peter';

        $model1 = $repo->create('test_update_mixed_collection');
        $model1->name = 'Will';
        $model1->last_name = 'Ferrel';

        $model2 = $repo->create('test_update_mixed_collection');
        $model2->name = 'Jack';
        $model2->last_name = 'Daniels';

        $repo->saveCollection([$preexistent, $model1, $model2]);

        $result = $repo->find('test_update_mixed_collection', "id = '{$preexistent->id}'");
        $this->assertEquals($result[0]->name, 'Peter');

        $result = $repo->find('test_update_mixed_collection', "id = '{$model1->id}'");
        $this->assertEquals($result[0]->name, 'Will');

        $result = $repo->find('test_update_mixed_collection', "id = '{$model2->id}'");
        $this->assertEquals($result[0]->name, 'Jack');
    }

    /**
     * @depends testConnect
     */
    public function testCreateTable()
    {
        $repo = FlexRepository::get();
        $repo->db->createTable('test_create_table');
        
        $fields = $repo->db->getTableFields('test_create_table');
        $this->assertEquals($fields[0]['Field'], 'id');
        $this->assertEquals($fields[0]['Type'], 'int');
        $this->assertEquals($fields[0]['Key'], 'PRI');
        $this->assertEquals($fields[0]['Extra'], 'auto_increment');
    }

    /**
     * @depends testCreateTable
     */
    public function testAddFieldsToTable()
    {
        $repo = FlexRepository::get();
        $repo->db->createTable('test_add_fields');

        $fields = [
            [
                'name' => 'name',
                'type' => 'TEXT',
                'null' => false
            ],
            [
                'name' => 'phone',
                'type' => 'INT(5)',
                'null' => false
            ]
        ];

        $repo->db->addFieldsToTable('test_add_fields', $fields);

        $tf = $repo->db->getTableFields('test_add_fields');

        $this->assertEquals($tf[1]['Field'], 'name');
        $this->assertEquals($tf[1]['Type'], 'text');

        $this->assertEquals($tf[2]['Field'], 'phone');
        $this->assertEquals($tf[2]['Type'], 'int');
    }

    public function testTableExists()
    {
        $repo = FlexRepository::get();
        $repo->db->createTable('test_table_exists');
        $exists = $repo->db->tableExists('test_table_exists');
        $this->assertTrue($exists);
    }

    public function testUpdateTableTypes()
    {
        $repo = FlexRepository::get();
        $model = $repo->create('test_update_table_types');
        $model->name = 'abcd';
        $model->phone = '12345';

        $repo->save($model);

        $model->meta()->add('fields', [
            'name' => ['type' => 'VARCHAR(150)', 'nullable' => true],
            'phone' => ['type' => 'VARCHAR(50)', 'nullable' => true],
        ]);

        $tableFields = $repo->db->getTableFields('test_update_table_types');

        $repo->db->updateTableTypes($model, $tableFields);

        $updatedFields = $repo->db->getTableFields('test_update_table_types');
        $this->assertEquals($updatedFields[1]['Field'], 'name');
        $this->assertEquals($updatedFields[1]['Type'], 'varchar(150)');

        $this->assertEquals($updatedFields[2]['Field'], 'phone');
        $this->assertEquals($updatedFields[2]['Type'], 'varchar(50)');
    }

    public function testQuery()
    {
        $repo = FlexRepository::get();

        $model = $repo->create('test_query');
        $model->name = 'TestQuery';
        $repo->save($model);

        $result = $repo->query('SELECT * FROM test_query WHERE name = :name', [':name' => 'TestQuery']);

        $this->assertCount(1, $result);
        $this->assertEquals('TestQuery', $result[0]->name);
    }

    public function testHydrate()
    {
        $repo = FlexRepository::get();
        $values = [
            ['table.name' => 'John', 'table.last_name' => 'Doe'],
            ['table.name' => 'Jack', 'table.last_name' => 'Daniels'],
            ['table.name' => 'Will', 'table.last_name' => 'Ferrel'],
        ];

        $hydrated = $repo->hydrate($values, 'some_table', 'Makiavelo\\Flex\\Flex');

        $this->assertCount(3, $hydrated);
        $this->assertEquals('Makiavelo\\Flex\\Flex', get_class($hydrated[0]));
        $this->assertEquals('John Doe', $hydrated[0]->name . ' ' . $hydrated[0]->last_name);

        $this->assertEquals('Makiavelo\\Flex\\Flex', get_class($hydrated[1]));
        $this->assertEquals('Jack Daniels', $hydrated[1]->name . ' ' . $hydrated[1]->last_name);

        $this->assertEquals('Makiavelo\\Flex\\Flex', get_class($hydrated[2]));
        $this->assertEquals('Will Ferrel', $hydrated[2]->name . ' ' . $hydrated[2]->last_name);
    }

    public function testCustomClassHydration()
    {
        $repo = FlexRepository::get();
        $values = [
            ['modelx.name' => 'John', 'modelx.last_name' => 'Doe'],
            ['modelx.name' => 'Jack', 'modelx.last_name' => 'Daniels'],
            ['modelx.name' => 'Will', 'modelx.last_name' => 'Ferrel'],
        ];

        $hydrated = $repo->hydrate($values, 'modelx', 'Modelx');

        $this->assertCount(3, $hydrated);
        $this->assertEquals('Modelx', get_class($hydrated[0]));
        $this->assertEquals('John Doe', $hydrated[0]->name . ' ' . $hydrated[0]->last_name);
        $this->assertEquals('modelx', $hydrated[0]->meta()->get('table'));

        $this->assertEquals('Modelx', get_class($hydrated[1]));
        $this->assertEquals('Jack Daniels', $hydrated[1]->name . ' ' . $hydrated[1]->last_name);
        $this->assertEquals('modelx', $hydrated[1]->meta()->get('table'));

        $this->assertEquals('Modelx', get_class($hydrated[2]));
        $this->assertEquals('Will Ferrel', $hydrated[2]->name . ' ' . $hydrated[2]->last_name);
        $this->assertEquals('modelx', $hydrated[2]->meta()->get('table'));
    }
}
