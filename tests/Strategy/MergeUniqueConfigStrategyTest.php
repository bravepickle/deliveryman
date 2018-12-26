<?php
/**
 * Date: 2018-12-26
 * Time: 16:57
 */

namespace DeliverymanTest\Strategy;

use Deliveryman\Strategy\MergeUniqueConfigStrategy;
use PHPUnit\Framework\TestCase;

class MergeUniqueConfigStrategyTest extends TestCase
{
    /**
     * @dataProvider mergeProvider
     * @param array $fallbackCfg
     * @param array $configs
     * @param array $expected
     */
    public function testMerge($fallbackCfg, $configs, $expected)
    {
        $merge = new MergeUniqueConfigStrategy($fallbackCfg);
        $actual = $merge->merge(...$configs);
        $this->assertEquals($expected, $actual);
    }

    public function testGetName()
    {
        $merge = new MergeUniqueConfigStrategy();
        $this->assertEquals('unique', $merge->getName());
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
                    ],
                    [
                        'colors' => ['orange'],
                        'name' => 'Vivat!',
                    ],
                ],
                'expected' => [
                    'luck' => 777,
                    'name' => 'Vivat!',
                    'colors' => ['orange', 'red', 'yellow', 'blue'],
                ],
            ],
            [
                'fallback' => [],
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
                    'name' => 'Vivat!',
                    'food' => [
                        'red' => 'apple',
                        'blue' => 'plum',
                        'yellow' => 'apricot',
                        'green' => ['peas'],
                    ],
                ],
            ],
        ];
    }
}
