<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository as FR;
use Makiavelo\Flex\Util\EnhancedPDO;

require_once(dirname(__FILE__) . '/../util/test_issues_models.php');

final class IssuesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $db = new \PDO($_ENV['PDO_DSN'], $_ENV['PDO_USER'], $_ENV['PDO_PASS']);
        $sql = file_get_contents(dirname(__FILE__) . '/fixtures/flex_repo_setup_before.sql');
        $qr = $db->exec($sql);

        $repo = FR::get();
        $repo->connect([
            'host' => '172.17.0.1',
            'db' => 'flex_test',
            'user' => 'root',
            'pass' => 'root'
        ]);
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

    public function testRelationIssue()
    {
        $user = new User();
        $user->setName('John');
        $user->setLastName('Doe');

        $status = FR::get()->save($user);
        $this->assertTrue($status);
    }

    /**
     * @depends testRelationIssue
     */
    public function testRelationIssueWithData()
    {
        $params = [':id' => 1];
        $users = FR::get()->find('user', 'id = :id', $params, ['class' => 'User']);
        $user = $users[0];

        $tags = FR::get()->query('SELECT * FROM tag', [], ['class' => 'Tag']);
        $this->assertCount(0, $tags);
        
        $tags = $user->getTags();
        $this->assertCount(0, $tags);
        $this->assertCount(1, $users);
    }
}
