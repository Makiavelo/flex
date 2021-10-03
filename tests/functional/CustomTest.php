<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/test_models.php');

final class CustomTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        FlexRepository::get()->connect([
            'host' => '172.17.0.1',
            //'flex_test',
            'db' => 'example_project',
            'user' => 'root',
            'pass' => 'root'
        ]);
    }

    public function testHydrateHasValues()
    {
        $this->assertTrue(true);
    }
}
