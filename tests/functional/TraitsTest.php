<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/traits_models.php');

final class TraitsTest extends TestCase
{
    protected function setUp(): void
    {
        $db = new \PDO($_ENV['PDO_DSN'], $_ENV['PDO_USER'], $_ENV['PDO_PASS']);
        $sql = file_get_contents(dirname(__FILE__) . '/../functional/fixtures/flex_repo_setup_before.sql');
        $qr = $db->exec($sql);

        FlexRepository::get()->connect([
            'host' => '172.17.0.1',
            'db' => 'flex_test',
            'user' => 'root',
            'pass' => 'root'
        ]);
    }

    public function testTimestampable()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestTimestampable();
        $model->setName('John');
        $model->setLastName('Doe');
        $repo->save($model);

        $this->assertIsString($model->getCreatedAt());
        $this->assertIsString($model->getUpdatedAt());
    }

    public function testSluggable()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestSluggable();
        $model->setName('John');
        $model->setLastName('Doe');
        $repo->save($model);

        $this->assertIsString($model->getSlug());
        $this->assertEquals('john-doe', $model->getSlug());
    }

    public function testGeopositioned()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestGeopositioned();
        $model->setName('John');
        $model->setLastName('Doe');

        // Florence
        $model->setLat('42.8201432');
        $model->setLng('10.7506802');
        $repo->save($model);

        $distance = round($model->getDistanceToRome()/1000); // Kms

        $this->assertIsNumeric($model->getLat());
        $this->assertIsNumeric($model->getLng());
        $this->assertIsNumeric($distance);
        
        $this->assertGreaterThan(158, $distance);
        $this->assertLessThan(162, $distance);
    }

    public function testTranslatable()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestTranslatable();
        $model->setName('John');
        $model->setLastName('Doe');
        $model->setDescription('Some cool description about John');
        $model->setShortDescription('Shorter description');

        $model->translations()->add(
            $model->translation('es', [
                'description' => 'Una descripcion copada sobre John',
                'short_description' => 'Una descripcion mas corta'
            ])
        );

        $status = $repo->save($model);
        $this->assertTrue($status);

        $es = $model->translations()->with(['locale' => 'es'])->fetch();
        $this->assertCount(1, $es);
        $this->assertEquals($es[0]->getDescription(), 'Una descripcion copada sobre John');
        $this->assertEquals($es[0]->getShortDescription(), 'Una descripcion mas corta');

        $model->translations()->add(
            $model->translation('fr', [
                'description' => 'I have to learn french...',
                'short_description' => 'Maybe...'
            ])
        );

        $status = $repo->save($model);
        $this->assertTrue($status);
        $this->assertCount(2, $model->getTranslations());
    }

    public function testTranslatableWithDb()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestTranslatable();
        $model->setName('John');
        $model->setLastName('Doe');
        $model->setDescription('Some cool description about John');
        $model->setShortDescription('Shorter description');

        $model->translations()->add(
            $model->translation('es', [
                'description' => 'Una descripcion copada sobre John',
                'short_description' => 'Una descripcion mas corta'
            ])
        );

        $model->translations()->add(
            $model->translation('fr', [
                'description' => 'I have to learn french...',
                'short_description' => 'Maybe...'
            ])
        );

        $status = $repo->save($model);
        $this->assertTrue($status);

        $result = $repo->query(
            'SELECT * FROM test_translatable JOIN test_translatable_translation ON test_translatable_translation.test_translatable_id = test_translatable.id',
            [],
            ['class' => 'TraitTestTranslatable', 'table' => 'test_translatable']);
        $this->assertCount(1, $result);
    }

    public function testTranslatableWithDbOrphans()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestTranslatable();
        $model->setName('John');
        $model->setLastName('Doe');
        $model->setDescription('Some cool description about John');
        $model->setShortDescription('Shorter description');

        $model->translations()->add(
            $model->translation('es', [
                'description' => 'Una descripcion copada sobre John',
                'short_description' => 'Una descripcion mas corta'
            ])
        );

        $model->translations()->add(
            $model->translation('fr', [
                'description' => 'I have to learn french...',
                'short_description' => 'Maybe...'
            ])
        );

        $status = $repo->save($model);
        $this->assertTrue($status);

        $result = $repo->query(
            'SELECT * FROM test_translatable WHERE id = :id',
            [':id' => $model->getId()],
            ['class' => 'TraitTestTranslatable', 'table' => 'test_translatable']);
        $this->assertCount(1, $result);

        $model = $result[0];

        $model->translations()->add(
            $model->translation('ch', [
                'description' => 'I have to learn chinese...',
                'short_description' => 'Maybe...'
            ])
        );

        $status = $repo->save($model);
        $this->assertTrue($status);

        $result = $repo->query(
            'SELECT * FROM test_translatable JOIN test_translatable_translation ON test_translatable_translation.test_translatable_id = test_translatable.id',
            [],
            ['class' => 'TraitTestTranslatable', 'table' => 'test_translatable']);
        $this->assertCount(1, $result);

        $model = $result[0];
        $this->assertCount(1, $model->getTranslations());

        $this->assertEquals('I have to learn chinese...', $model->getTranslations()[0]->getDescription());
    }

    public function testVersionable()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestVersionable();
        $model->setName('John');
        $model->setLastName('Doe');
        $model->setDescription('Some cool description');

        $status = $repo->save($model);
        $this->assertTrue($status);

        $versions = $model->getVersions();
        $this->assertCount(1, $versions);

        $model->setName('Jack');
        $status = $repo->save($model);
        $this->assertTrue($status);

        $this->assertCount(2, $model->getVersions());

        $model->changeVersion(1);
        $this->assertEquals('John', $model->getName());

        $model->changeVersion(2);
        $this->assertEquals('Jack', $model->getName());

        $model->versions()->clear();
        $versions = $model->getVersions();
        $this->assertCount(0, $versions);
    }

    public function testVersionableWithDb()
    {
        $repo = FlexRepository::get();

        $model = new TraitTestVersionable();
        $model->setName('John');
        $model->setLastName('Doe');
        $model->setDescription('Some cool description');

        $status = $repo->save($model);
        $this->assertTrue($status);

        $versions = $model->getVersions();
        $this->assertCount(1, $versions);

        $model->setName('Jack');
        $status = $repo->save($model);
        $this->assertTrue($status);

        $model->setLastName('Daniels');
        $model->setDescription('Some whiskey...');
        $status = $repo->save($model);
        $this->assertTrue($status);

        $result = $repo->query(
            'SELECT * FROM test_versionable JOIN test_versionable_version ON test_versionable_version.test_versionable_id = test_versionable.id',
            [],
            ['class' => 'TraitTestVersionable', 'table' => 'test_versionable']);
        $this->assertCount(1, $result);

        $model = $result[0];

        $model->changeVersion(1);
        $status = $repo->save($model);
        $this->assertTrue($status);
        $this->assertEquals('John', $model->getName());
        $this->assertEquals('Doe', $model->getLastName());
        $this->assertEquals('4', $model->getVersion());

        $model->setName('Will');
        $status = $repo->save($model);
        $this->assertTrue($status);
        $this->assertEquals('Will', $model->getName());
        $this->assertEquals('5', $model->getVersion());
    }
}
