<?php
/**
 * Date: 2018-12-26
 * Time: 17:15
 */

namespace DeliverymanTest\Strategy;

use Deliveryman\Strategy\MergeFirstConfigStrategy;
use Deliveryman\Strategy\MergeIgnoreConfigStrategy;
use PHPUnit\Framework\TestCase;

/**
 * Class MergeIgnoreConfigStrategyTest
 * @package DeliverymanTest\Strategy
 */
class MergeIgnoreConfigStrategyTest extends TestCase
{
    /**
     * @dataProvider mergeProvider
     * @param array $fallbackCfg
     * @param array $configs
     * @param array $expected
     */
    public function testMerge($fallbackCfg, $configs, $expected)
    {
        $merge = new MergeIgnoreConfigStrategy($fallbackCfg);
        $actual = $merge->merge(...$configs);
        $this->assertEquals($expected, $actual);
    }

    public function testGetName()
    {
        $merge = new MergeIgnoreConfigStrategy();
        $this->assertEquals('ignore', $merge->getName());
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
                    'name' => 'victory',
                    'colors' => ['blue', 'red'],
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
                    'enabled' => true,
                ],
            ],
        ];
    }
}
