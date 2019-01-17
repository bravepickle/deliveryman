<?php
/**
 * Date: 2018-12-26
 * Time: 17:15
 */

namespace DeliverymanTest\Strategy;

use Deliveryman\Strategy\MergeFirstConfigStrategy;
use PHPUnit\Framework\TestCase;

class MergeFirstConfigStrategyTest extends TestCase
{
    /**
     * @dataProvider mergeProvider
     * @param array $fallbackCfg
     * @param array $configs
     * @param array $expected
     */
    public function testMerge($fallbackCfg, $configs, $expected)
    {
        $merge = new MergeFirstConfigStrategy($fallbackCfg);
        $actual = $merge->merge(...$configs);
        $this->assertEquals($expected, $actual);
    }

    public function testGetName()
    {
        $merge = new MergeFirstConfigStrategy();
        $this->assertEquals('first', $merge->getName());
    }

    public function mergeProvider()
    {
        return [
            [
                'fallback' => [
                    'luck' => 777,
                    'name' => 'victory',
                    'colors' => ['blue', 'red'],
                ],
                'input' => [
                    [
                        'colors' => ['red', 'yellow'],
                        'speed' => '100mph'
                    ],
                    [
                        'colors' => ['orange'],
                        'name' => 'Vivat!',
                    ],
                ],
                'expected' => [
                    'luck' => 777,
                    'name' => 'Vivat!',
                    'colors' => ['orange'],
                    'speed' => '100mph',
                ],
            ],
            [
                'fallback' => [
                    'enabled' => true,
                ],
                'input' => [
                    [
                        'food' => ['red' => 'apple', 'yellow' => 'banana', 'green' => ['salad']],
                    ],
                    [
                        'food' => [
                            'blue' => 'plum',
                            'yellow' => 'apricot',
                            'green' => ['peas'],
                        ],
                        'name' => 'Vivat!',
                    ],
                ],
                'expected' => [
                    'food' => [
                        'blue' => 'plum',
                        'yellow' => 'apricot',
                        'green' => ['peas'],
                    ],
                    'name' => 'Vivat!',
                    'enabled' => true,
                ],
            ],
        ];
    }
}
