<?php
/**
 * Date: 2018-12-23
 * Time: 23:38
 */

namespace DeliverymanTest\Channel\HttpGraph;

use Deliveryman\Channel\HttpGraph\GraphNode;
use Deliveryman\Channel\HttpGraph\GraphNodeCollection;
use Deliveryman\Channel\HttpGraph\GraphTreeBuilder;
use Deliveryman\Channel\HttpGraph\GraphvizDumper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GraphvizDumperTest
 * @package DeliverymanTest\Channel\HttpGraph
 */
class GraphvizDumperTest extends TestCase
{
    /**
     * Data sets for data provider taken from parsed files
     * @var array
     */
    protected static $fixtures;

    /**
     * @dataProvider dumpProvider
     * @param array|GraphNode[] $input
     * @param array $options
     * @param string $expected
     * @throws \Deliveryman\Exception\LogicException
     */
    public function testDump(array $input, array $options, string $expected)
    {
        $collection = new GraphNodeCollection((new GraphTreeBuilder())->buildNodes($input));

        $dumper = new GraphvizDumper($options);
        $actual = $dumper->dump($collection);

        $this->assertEquals(trim($expected), trim($actual));
    }

    public function dumpProvider()
    {
        return $this->prepareProviderData(self::getFixtures(__FUNCTION__));
    }

    /**
     * @param array $dataSet
     * @return array
     */
    protected function prepareProviderData(array $dataSet): array
    {
        foreach ($dataSet as &$data) {
            $input = [];
            foreach ($data['input'] as $datum) {
                $input[] = new GraphNode($datum['id'], $datum['refs']);
            }

            $data['input'] = $input;
        }

        return $dataSet;
    }

    /**
     * Load data fixtures for the class
     * @param string $name
     * @return array|mixed
     */
    public static function getFixtures(string $name)
    {
        if (self::$fixtures !== null) {
            return self::$fixtures[$name] ?? [];
        }

        self::$fixtures = Yaml::parseFile(__DIR__ . '/../../Resources/fixtures/graphviz_dumper.fixtures.yaml');

        return self::$fixtures[$name] ?? [];
    }
}