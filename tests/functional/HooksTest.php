<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/hook_models.php');

final class HooksTest extends TestCase
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

    public function testPreSave()
    {
        $repo = FlexRepository::get();

        $user = new UserHook();
        $user->setName('John');
        $user->setLastName('Doe');
        $user->meta()->add('fail', true);

        $status = $repo->save($user);
        $this->assertFalse($status);
        $this->assertIsArray($user->meta()->get('errors'));
        $this->assertEquals('Failure forced', $user->meta()->get('errors->0'));

        $user = new UserHook();
        $user->setName('John');
        $user->meta()->add('scope', 'new');

        $status = $repo->save($user);
        $this->assertFalse($status);
        $this->assertIsArray($user->meta()->get('errors'));
        $this->assertEquals('Last name required', $user->meta()->get('errors->0'));

        $user = new UserHook();
        $user->setName('John');
        $user->meta()->add('scope', 'test');

        $status = $repo->save($user);
        $this->assertTrue($status);
    }

    public function testPostSave()
    {
        $repo = FlexRepository::get();

        $user = new UserHook();
        $user->setName('John');
        $user->setLastName('Doe');
        $repo->save($user);

        $this->assertEquals('postSave', $user->meta()->get('action'));
    }

    public function testPreDelete()
    {
        $repo = FlexRepository::get();

        $user = new UserHook();
        $user->setName('John');
        $user->setLastName('Doe');
        $repo->save($user);

        $user->meta()->add('scope', 'testDeleteFailure');
        $status = $repo->delete($user);
        $this->assertFalse($status);
        $this->assertIsArray($user->meta()->get('errors'));

        $user->meta()->add('scope', '');
        $status = $repo->delete($user);
        $this->assertTrue($status);
    }

    public function testPostDelete()
    {
        $repo = FlexRepository::get();

        $user = new UserHook();
        $user->setName('John');
        $user->setLastName('Doe');
        $repo->save($user);

        $repo->delete($user);
        $this->assertEquals('postDelete', $user->meta()->get('action'));
    }
}
