<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/test_models.php');

final class FlexRelationsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $db = new \PDO($_ENV['PDO_DSN'], $_ENV['PDO_USER'], $_ENV['PDO_PASS']);
        $sql = file_get_contents(dirname(__FILE__) . '/fixtures/flex_repo_setup_before.sql');
        $qr = $db->exec($sql);

        FlexRepository::get()->connect(
            '172.17.0.1',
            'flex_test',
            //'example_project',
            'root',
            'root'
        );
    }

    public static function tearDownAfterClass(): void
    {
        $db = new \PDO($_ENV['PDO_DSN'], $_ENV['PDO_USER'], $_ENV['PDO_PASS']);
        $sql = file_get_contents(dirname(__FILE__) . '/fixtures/flex_tear_down_after.sql');
        $qr = $db->exec($sql);
    }

    public function testBelongs()
    {
        $repo = FlexRepository::get();

        $company = new Company();
        $company->setName('test_company');
        $repo->save($company);

        $user = new User();
        $user->setCompany($company);
        $user->setName('John');
        $user->setLastName('Doe');

        $repo->save($user);

        $this->assertEquals(get_class($user->getCompany()), 'Company');
        $this->assertIsNumeric($company->getId());
        $this->assertEquals($user->getCompany()->getId(), $company->getId());
        $this->assertEquals($user->getCompany()->getName(), 'test_company');
    }

    public function testNestedRelation()
    {
        $repo = FlexRepository::get();

        $company = new Company();
        $company->setName('test_company_2');

        $user = new User();
        $user->setCompany($company);
        $user->setName('Jack');
        $user->setLastName('Daniels');

        $repo->save($user);

        $this->assertEquals(get_class($user->getCompany()), 'Company');
        $this->assertIsNumeric($company->getId());
        $this->assertEquals($user->getCompany()->getId(), $company->getId());
        $this->assertEquals($user->getCompany()->getName(), 'test_company_2');
    }

    public function testHasRelation()
    {
        $repo = FlexRepository::get();

        $company = new Company();
        $company->setName('test_company_2');

        $user1 = new User();
        $user1->setName('Jack');
        $user1->setLastName('Daniels');

        $user2 = new User();
        $user2->setName('John');
        $user2->setLastName('Doe');

        $company->setUsers([$user1, $user2]);

        $repo->save($company);

        $this->assertIsNumeric($company->getId());
        $this->assertCount(2, $company->getUsers());
        $this->assertEquals($company->getId(), $company->getUsers()[0]->company_id);
        $this->assertEquals($company->getId(), $company->getUsers()[1]->company_id);
    }

    public function testHydrateRelations()
    {
        $repo = FlexRepository::get();

        $company = new Company();
        $company->setName('test_company_3');
        
        $user1 = new User();
        $user1->setName('Jack');
        $user1->setLastName('Daniels');

        $user2 = new User();
        $user2->setName('John');
        $user2->setLastName('Doe');

        $company->setUsers([$user1, $user2]);

        $repo->save($company);

        $query = "SELECT * FROM user JOIN company ON company.id = user.company_id WHERE company.id = '" . $company->getId() . "'";
        $options = [
            'table' => 'user',
            'class' => 'User'
        ];

        $result = $repo->query($query, [], $options);
        $this->assertCount(2, $result);

        $this->assertInstanceOf('User', $result[0]);
        $this->assertInstanceOf('Company', $result[0]->getCompany());

        $this->assertInstanceOf('User', $result[1]);
        $this->assertInstanceOf('Company', $result[1]->getCompany());
    }

    public function testHydrateNestedHasRelation()
    {
        $result = [
            [
                'company.id' => 1,
                'company.name' => 'TestCompany',
                'user.id' => 1,
                'user.company_id' => 1,
                'user.name' => 'John',
                'user.last_name' => 'Doe'
            ],
            [
                'company.id' => 1,
                'company.name' => 'TestCompany',
                'user.id' => 2,
                'user.company_id' => 1,
                'user.name' => 'Jack',
                'user.last_name' => 'Daniels'
            ],
            [
                'company.id' => 1,
                'company.name' => 'TestCompany',
                'user.id' => 3,
                'user.company_id' => 1,
                'user.name' => 'Will',
                'user.last_name' => 'Ferrel'
            ],
            [
                'company.id' => 2,
                'company.name' => 'TestCompany2',
                'user.id' => 4,
                'user.company_id' => 2,
                'user.name' => 'John',
                'user.last_name' => 'Jones'
            ],
            [
                'company.id' => 2,
                'company.name' => 'TestCompany2',
                'user.id' => 5,
                'user.company_id' => 2,
                'user.name' => 'Joe',
                'user.last_name' => 'Frazier'
            ],
            [
                'company.id' => 2,
                'company.name' => 'TestCompany2',
                'user.id' => 6,
                'user.company_id' => 2,
                'user.name' => 'Mike',
                'user.last_name' => 'Tyson'
            ],
        ];

        $hydrated = FlexRepository::get()->hydrate($result, 'company', 'Company');
        $company1 = $hydrated[0];
        $company2 = $hydrated[1];

        $users1 = $company1->getUsers();
        $this->assertEquals(1, $company1->getId());
        $this->assertEquals('TestCompany', $company1->getName());
        $this->assertCount(3, $users1);
        $this->assertEquals('John', $users1[0]->getName());
        $this->assertEquals('Jack', $users1[1]->getName());
        $this->assertEquals('Will', $users1[2]->getName());

        $users2 = $company2->getUsers();
        $this->assertEquals(2, $company2->getId());
        $this->assertEquals('TestCompany2', $company2->getName());
        $this->assertCount(3, $users2);
        $this->assertEquals('John', $users2[0]->getName());
        $this->assertEquals('Joe', $users2[1]->getName());
        $this->assertEquals('Mike', $users2[2]->getName());
    }

    public function testSaveAlias()
    {
        $repo = FlexRepository::get();

        $company = new CompanyX();
        $company->name = 'TestCompanyX';

        $owner = new UserX();
        $owner->name = 'John';
        $owner->last_name = 'Doe';

        $manager = new UserX();
        $manager->name = 'Jack';
        $manager->last_name = 'Daniels';

        $company->setOwner($owner);
        $company->setManager($manager);

        $repo->save($company);

        $this->assertIsNumeric($company->getId());
        $this->assertIsNumeric($company->getOwner()->getId());
        $this->assertIsNumeric($company->getManager()->getId());

        $this->assertEquals($company->getOwner()->getId(), $company->owner_id);
        $this->assertEquals($company->getManager()->getId(), $company->manager_id);
        
    }

    public function testHydrateAlias()
    {
        $repo = FlexRepository::get();

        $result = [
            [
                'companyx.id' => 1,
                'companyx.name' => 'TestCompany',
                'owner.id' => 1,
                'owner.name' => 'John',
                'owner.last_name' => 'Doe',
                'manager.id' => 2,
                'manager.name' => 'Jack',
                'manager.last_name' => 'Daniels',
            ]
        ];

        $hydrated = $repo->hydrate($result, 'companyx', 'CompanyX');

        $this->assertNotNull($hydrated);
        $this->assertCount(1, $hydrated);

        $company = $hydrated[0];
        $this->assertInstanceOf('CompanyX', $company);
        $this->assertInstanceOf('UserX', $company->getOwner());
        $this->assertInstanceOf('UserX', $company->getManager());
        $this->assertEquals('1', $company->owner_id);
        $this->assertEquals('1', $company->getOwnerId());
        $this->assertEquals('John', $company->getOwner()->getName());
        $this->assertEquals('Doe', $company->getOwner()->getLastName());

        $this->assertEquals('2', $company->manager_id);
        $this->assertEquals('2', $company->getManagerId());
        $this->assertEquals('Jack', $company->getManager()->getName());
        $this->assertEquals('Daniels', $company->getManager()->getLastName());

        $this->assertEquals($company->getOwner()->getId(), '1');
        $this->assertEquals($company->getManager()->getId(), '2');
    }

    public function testHydrateSelfReference()
    {
        $result = [
            [
                'usery.id' => 2,
                'usery.name' => 'John',
                'usery.last_name' => 'Doe',
                'usery.parent_id' => 1,
                'parent.id' => 1,
                'parent.name' => 'Jack',
                'parent.last_name' => 'Daniels',
            ]
        ];

        $model = new UserY();
        $model->hydrate($result);

        $this->assertNotNull($model);
        $this->assertInstanceOf('UserY', $model);
        $this->assertInstanceOf('UserY', $model->getParent());
        $this->assertEquals($model->getParentId(), $model->getParent()->getId());
    }

    public function testInsertSelfReference()
    {
        $user = new UserY();
        $user->setName('John');
        $user->setLastName('Doe');

        $parent = new UserY();
        $parent->setName('Jack');
        $parent->setLastName('Daniels');

        $user->setParent($parent);

        $result = FlexRepository::get()->save($user);

        $this->assertNotNull($result);
    }

    /**
     * @depends testInsertSelfReference
     */
    public function testSelfReferenceQueryAndHydration()
    {
        $repo = FlexRepository::get();

        $query = "SELECT * FROM usery JOIN usery parent ON parent.id = usery.parent_id WHERE usery.id = '2'";
        $options = [
            'table' => 'usery',
            'class' => 'UserY'
        ];

        $result = $repo->query($query, [], $options);

        $this->assertNotNull($result);
        $this->assertInstanceOf('UserY', $result[0]);
        $this->assertInstanceOf('UserY', $result[0]->getParent());
        $this->assertEquals($result[0]->getParentId(), $result[0]->getParent()->getId());
    }

    public function testManyToManyEmulation()
    {
        $repo = FlexRepository::get();

        $users = [];
        $user1 = new UserA();
        $user1->setName('John');
        $users[] = $user1;

        $user2 = new UserA();
        $user2->setName('Jack');
        $users[] = $user2;

        $user3 = new UserA();
        $user3->setName('Will');
        $users[] = $user3;

        $tags = [];
        $tag1 = new Tag();
        $tag1->setName('Tag 1');
        $tags[] = $tag1;

        $tag2 = new Tag();
        $tag2->setName('Tag 2');
        $tags[] = $tag2;

        $tag3 = new Tag();
        $tag3->setName('Tag 3');
        $tags[] = $tag3;

        foreach ($users as $u => $user) {
            $repo->save($users[$u]);
        }

        foreach ($tags as $t => $tag) {
            $repo->save($tags[$t]);
        }

        foreach ($users as $ukey =>$user) {
            $userTags = [];
            foreach ($tags as $tkey => $tag) {
                $userTag = new UserTag();
                $userTag->setUserId($user->getId());
                $userTag->setTagId($tag->getId());
                $userTags[] = $userTag;
            }

            $users[$ukey]->setUserTags($userTags);
            $repo->save($users[$ukey]);
        }

        $this->assertTrue(true);
    }

    /**
     * @depends testManyToManyEmulation
     */
    public function testHydrateManyToManyEmulated()
    {
        $repo = FlexRepository::get();

        $query = "SELECT * FROM usera JOIN usera_tag ON usera_tag.user_id = usera.id JOIN tag ON usera_tag.tag_id = tag.id";
        $result = $repo->query($query, [], ['table' => 'usera', 'class' => 'UserA']);

        $this->assertCount(3, $result);
        $this->assertInstanceOf('UserA', $result[0]);
        $this->assertInstanceOf('UserA', $result[1]);
        $this->assertInstanceOf('UserA', $result[2]);

        $this->assertInstanceOf('UserTag', $result[0]->getUserTags()[0]);
        $this->assertInstanceOf('UserTag', $result[0]->getUserTags()[1]);
        $this->assertInstanceOf('UserTag', $result[0]->getUserTags()[2]);

        $this->assertInstanceOf('Tag', $result[0]->getUserTags()[0]->getTag());
        $this->assertInstanceOf('Tag', $result[0]->getUserTags()[1]->getTag());
        $this->assertInstanceOf('Tag', $result[0]->getUserTags()[2]->getTag());

        $this->assertEquals('1', $result[0]->getUserTags()[0]->getTag()->getId());
        $this->assertEquals('2', $result[0]->getUserTags()[1]->getTag()->getId());
        $this->assertEquals('3', $result[0]->getUserTags()[2]->getTag()->getId());

        $this->assertInstanceOf('UserTag', $result[1]->getUserTags()[0]);
        $this->assertInstanceOf('UserTag', $result[1]->getUserTags()[1]);
        $this->assertInstanceOf('UserTag', $result[1]->getUserTags()[2]);

        $this->assertInstanceOf('Tag', $result[1]->getUserTags()[0]->getTag());
        $this->assertInstanceOf('Tag', $result[1]->getUserTags()[1]->getTag());
        $this->assertInstanceOf('Tag', $result[1]->getUserTags()[2]->getTag());

        $this->assertEquals('1', $result[1]->getUserTags()[0]->getTag()->getId());
        $this->assertEquals('2', $result[1]->getUserTags()[1]->getTag()->getId());
        $this->assertEquals('3', $result[1]->getUserTags()[2]->getTag()->getId());

        $this->assertInstanceOf('UserTag', $result[2]->getUserTags()[0]);
        $this->assertInstanceOf('UserTag', $result[2]->getUserTags()[1]);
        $this->assertInstanceOf('UserTag', $result[2]->getUserTags()[2]);

        $this->assertInstanceOf('Tag', $result[2]->getUserTags()[0]->getTag());
        $this->assertInstanceOf('Tag', $result[2]->getUserTags()[1]->getTag());
        $this->assertInstanceOf('Tag', $result[2]->getUserTags()[2]->getTag());

        $this->assertEquals('1', $result[2]->getUserTags()[0]->getTag()->getId());
        $this->assertEquals('2', $result[2]->getUserTags()[1]->getTag()->getId());
        $this->assertEquals('3', $result[2]->getUserTags()[2]->getTag()->getId());
    }

    public function testHasAndBelongs()
    {
        $repo = FlexRepository::get();

        $user = new UserB();
        $user->setName('John');

        $tag1 = new TagB();
        $tag1->setName('Tag 1');

        $tag2 = new TagB();
        $tag2->setName('Tag 2');

        $user->setTags([$tag1, $tag2]);

        $status = $repo->save($user);

        $this->assertTrue($status);
    }

    /**
     * @depends testHasAndBelongs
     */
    public function testHydrateHasAndBelongs()
    {
        $repo = FlexRepository::get();

        $query = "SELECT * FROM userb JOIN userb_tagb ON userb_tagb.user_id = userb.id JOIN tagb ON userb_tagb.tag_id = tagb.id";
        $result = $repo->query($query, [], ['table' => 'userb', 'class' => 'UserB']);

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf('UserB', $result[0]);
        $this->assertCount(2, $result[0]->getTags());
        $this->assertInstanceOf('TagB', $result[0]->getTags()[0]);
        $this->assertInstanceOf('TagB', $result[0]->getTags()[1]);

        // Checking that there aren't circular dependency issues
        $this->assertNull($result[0]->getTags()[0]->getRelation('Users')['instance']);
        $this->assertNull($result[0]->getTags()[1]->getRelation('Users')['instance']);
    }

    /**
     * @depends testHydrateHasAndBelongs
     */
    public function testUpdateRelatedCollection()
    {
        $repo = FlexRepository::get();

        $query = "SELECT * FROM userb JOIN userb_tagb ON userb_tagb.user_id = userb.id JOIN tagb ON userb_tagb.tag_id = tagb.id";
        $result = $repo->query($query, [], ['table' => 'userb', 'class' => 'UserB']);

        $user = $result[0];

        $user->getTags()[0]->setName('Edited Tag');
        $user->setTags([$user->getTags()[0]]);
        $repo->save($user);

        $query = "SELECT * FROM userb JOIN userb_tagb ON userb_tagb.user_id = userb.id JOIN tagb ON userb_tagb.tag_id = tagb.id";
        $result = $repo->query($query, [], ['table' => 'userb', 'class' => 'UserB']);
        $user = $result[0];

        $this->assertCount(1, $user->getTags());
        $this->assertInstanceOf('TagB', $user->getTags()[0]);
        $this->assertEquals('Edited Tag', $user->getTags()[0]->getName());

        $user->setTags([]);
        $repo->save($user);

        $query = "SELECT * FROM userb LEFT JOIN userb_tagb ON userb_tagb.user_id = userb.id LEFT JOIN tagb ON userb_tagb.tag_id = tagb.id";
        $result = $repo->query($query, [], ['table' => 'userb', 'class' => 'UserB']);

        $user = $result[0];
        $this->assertCount(0, $user->getTags());

        // Just check nothing got messed up
        $this->assertIsNumeric($user->getId());
        $this->assertIsString($user->getName());
    }

    public function testUpdateHasRelation()
    {
        $repo = FlexRepository::get();

        $company = new CompanyB();
        $company->setName('TestCompanyB');
        
        $user1 = new UserC();
        $user1->setName('John');

        $user2 = new UserC();
        $user2->setName('Jack');

        $company->setUsers([$user1, $user2]);

        $repo->save($company);

        $query = "SELECT * FROM companyb JOIN userc ON userc.company_id = companyb.id WHERE companyb.id = '{$company->id}'";
        $result = $repo->query($query, [], ['table' => 'companyb', 'class' => 'CompanyB']);

        $company = $result[0];
        $this->assertCount(2, $company->getUsers());
        $company->setUsers([$company->getUsers()[0]]);
        $repo->save($company);

        $query = "SELECT * FROM companyb JOIN userc ON userc.company_id = companyb.id WHERE companyb.id = '{$company->id}'";
        $result = $repo->query($query, [], ['table' => 'companyb', 'class' => 'CompanyB']);

        $company = $result[0];
        $this->assertCount(1, $company->getUsers());
        $company->setUsers([]);
        $repo->save($company);

        $query = "SELECT * FROM companyb LEFT JOIN userc ON userc.company_id = companyb.id WHERE companyb.id = '{$company->id}'";
        $result = $repo->query($query, [], ['table' => 'companyb', 'class' => 'CompanyB']);

        $company = $result[0];
        $this->assertCount(0, $company->getUsers());

        $this->assertTrue(true);
    }

    /**
     * @group eb1
     */
    public function testDupedHasAndBelongs()
    {
        $repo = FlexRepository::get();

        $user = new UserD();
        $user->setName('Duper');

        $tag1 = new TagD();
        $tag1->setName('Duped');

        $user->setTags([$tag1, $tag1]);

        $this->expectException(\Exception::class);
        $status = $repo->save($user);

        $this->assertFalse($status);
    }
}
