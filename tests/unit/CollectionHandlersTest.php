<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/test_models.php');

final class CollectionHandlersTest extends TestCase
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

    public function testPrimitives()
    {
        $user = new User();
        $user->setName('John');
        $user->setLastName('Doe');

        $company = new Company();
        $company->setName('Test Company');

        $company->users()->add($user);
        $this->assertCount(1, $company->getUsers());

        $bool = $company->users()->with(['name' => 'John'])->exists();
        $this->assertTrue($bool);

        $bool = $company->users()->with(['name' => 'John'])->not()->exists();
        $this->assertFalse($bool);
        
        $users = $company->users()->with(['name' => 'John'])->fetch();
        $this->assertCount(1, $users);

        $company->users()->with(['name' => 'John'])->remove();
        $this->assertCount(0, $company->getUsers());

        $company->users()->add($user);
        $this->assertCount(1, $company->getUsers());

        $company->users()->clear();
        $this->assertCount(0, $company->getUsers());

        $this->assertTrue(true);
    }

    public function testStuff()
    {
        $user1 = new User();
        $user1->setId(1);
        $user1->setName('John');
        $user1->setLastName('Doe');

        $user2 = new User();
        $user2->setName('Jack');
        $user2->setLastName('Daniels');

        $user3 = new User();
        $user3->setName('Will');
        $user3->setLastName('Ferrel');

        $company = new Company();
        $company->setName('Test Company');

        $company->users()->add($user1);
        $company->users()->add($user2);
        $company->users()->add($user3);
        $this->assertCount(3, $company->getUsers());

        $jds = $company
            ->users()
            ->with(function ($model) {
                $a = $model->getName()[0];
                $b = $model->getLastName()[0];
                if ($a === 'J' && $b === 'D') {
                    return true;
                }
                return false;
            })
            ->fetch();

        $this->assertCount(2, $jds);

        $wfs = $company
            ->users()
            ->with(function ($model) {
                $a = $model->getName()[0];
                $b = $model->getLastName()[0];
                if ($a === 'W' && $b === 'F') {
                    return true;
                }
                return false;
            })
            ->fetch();

        $empty = $company
            ->users()
            ->with(function ($model) {
                return false;
            })
            ->fetch();

        $this->assertCount(0, $empty);

        $bool = $company->users()->with($user1)->exists();
        $this->assertTrue($bool);

        $users = $company->users()->with($user1)->not()->fetch();
        $this->assertCount(2, $users);
    }

    public function testWithDb()
    {
        $repo = FlexRepository::get();

        $user1 = new User();
        $user1->setName('John');
        $user1->setLastName('Doe');

        $user2 = new User();
        $user2->setName('Jack');
        $user2->setLastName('Daniels');

        $user3 = new User();
        $user3->setName('Will');
        $user3->setLastName('Ferrel');

        $company = new Company();
        $company->setName('Test Company');

        $company->users()->add($user1);
        $company->users()->add($user2);
        $company->users()->add($user3);

        $repo->save($company);

        $result = $this->fetchAll();
        $this->assertCount(1, $result);

        $company = $result[0];
        $this->assertCount(3, $company->getUsers());

        $result = $company->users()->fetch();
        $this->assertCount(3, $result);
        
        $result = $company->users()->with(['name' => 'Will'])->fetch();
        $this->assertCount(1, $result);

        $company->users()->with(['name' => 'Will'])->remove();
        $repo->save($company);

        $result = $this->fetchAll();
        $company = $result[0];
        $this->assertCount(2, $company->getUsers());

        $this->assertTrue($company->users()->with(['name' => 'John'])->exists());
        $this->assertTrue($company->users()->with(['name' => 'Jack'])->exists());
        $this->assertFalse($company->users()->with(['name' => 'Will'])->exists());
    }

    public function testWithMoreElements()
    {
        $repo = FlexRepository::get();

        $users = [];
        for ($i = 0; $i < 30; $i++) {
            if ($i < 10) {
                $user = new User();
                $user->setName('John');
                $user->setLastName('Doe');
            } elseif ($i < 20) {
                $user = new User();
                $user->setName('Jack');
                $user->setLastName('Daniels');
            } else {
                $user = new User();
                $user->setName('Will');
                $user->setLastName('Ferrel');
            }

            $users[] = $user;
        }

        $company = new Company();
        $company->setName('Test Company');

        $company->setUsers($users);
        $repo->save($company);

        $result = $this->fetchAll();
        $company = $result[0];

        $users = $company->users()->fetch();
        $this->assertCount(30, $users);

        $users = $company->users()->with(['name' => 'John'])->fetch();
        $this->assertCount(10, $users);

        $users = $company->users()->with(['name' => 'Jack'])->fetch();
        $this->assertCount(10, $users);

        $users = $company->users()->with(['name' => 'Will'])->fetch();
        $this->assertCount(10, $users);

        $company->users()->with(['name' => 'Will', 'last_name' => 'Ferrel'])->remove();
        $repo->save($company);

        $result = $this->fetchAll();
        $company = $result[0];

        $users = $company->getUsers();
        $this->assertCount(20, $users);
    }

    protected function fetchAll()
    {
        $query = "SELECT * FROM company JOIN user ON company.id = user.company_id";
        $options = ['table' => 'company', 'class' => 'Company'];

        return FlexRepository::get()->query($query, [], $options);
    }
}
