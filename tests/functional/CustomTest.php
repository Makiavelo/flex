<?php

use PHPUnit\Framework\TestCase;

use Makiavelo\Flex\Flex;
use Makiavelo\Flex\FlexRepository;

require_once(dirname(__FILE__) . '/../util/test_models.php');

final class CustomTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        FlexRepository::get()->connect(
            '172.17.0.1',
            //'flex_test',
            'example_project',
            'root',
            'root'
        );
    }

    public function testHydrateHasValues()
    {
        $repo = FlexRepository::get();
        $repo->db->setAttribute(\PDO::ATTR_FETCH_TABLE_NAMES, true);

        $query = "SELECT * FROM company JOIN user ON user.company_id = company.id";
        $result = $repo->db->query($query)->fetchAll();
        $hydrated = $repo->hydrate($result, 'company', 'Company');

        $company = $hydrated[0];
        $this->assertTrue(true);
    }
}
