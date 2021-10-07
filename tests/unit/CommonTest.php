<?php

use PHPUnit\Framework\TestCase;
use Makiavelo\Flex\Util\Common;

final class CommonTest extends TestCase
{
    public function testRemove()
    {
        $collection = [
            'name' => 'John',
            'tags' => [
                'tag 1',
                'tag 2',
                'tag 3'
            ],
            'nested' => [
                'more' => [
                    'nesting' => [
                        'and' => [
                            'more' => 'lorem ipsum'
                        ]
                    ]
                ]
            ],
        ];

        Common::remove($collection, 'name');
        $this->assertFalse(isset($collection['name']));

        Common::remove($collection, 'nested->more->nesting');
        $this->assertNull(Common::get($collection, 'nested->more->nesting'));
        $this->assertIsArray(Common::get($collection, 'nested->more'));

        Common::remove($collection, 'tags->0');
        $this->assertCount(2, $collection['tags']);

        Common::remove($collection, 'nested');
        $this->assertNull(Common::get($collection, 'nested'));
    }
}
