<?php
/**
 * Date: 2018-12-23
 * Time: 18:36
 */

namespace DeliverymanTest\Channel\HttpGraph;


use Deliveryman\Channel\HttpGraph\GraphTreeBuilder;
use Deliveryman\Channel\HttpGraph\GraphNode;
use Deliveryman\Channel\HttpGraph\GraphNodeCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GraphNodeCollectionTest
 * @package DeliverymanTest\Channel\HttpGraph
 */
class GraphNodeCollectionTest extends TestCase
{
    /**
     * Data sets for data provider taken from parsed files
     * @var array
     */
    protected static $fixtures;

    public function testSetItems()
    {
        $nodes = [new GraphNode(), new GraphNode()];
        $collection = new GraphNodeCollection();

        $this->assertIsArray($collection->getNodes());
        $this->assertEmpty($collection->getNodes());

        $collection->setNodes($nodes);
        $this->assertEquals($nodes, $collection->getNodes());
    }

    /**
     * @throws \Deliveryman\Exception\LogicException
     */
    public function testArrowTailsIterator()
    {
        $nodes = [
            (new GraphNode())->setId('foo')->setReferenceIds(['bar']),
            (new GraphNode())->setId('bar'),
            (new GraphNode())->setId('baz'),
        ];
        $builder = new GraphTreeBuilder();
        $collection = new GraphNodeCollection($builder->buildNodes($nodes));

        $actualTailIds = [];
        /** @var GraphNode $node */
        foreach ($collection->arrowTailsIterator() as $node) {
            $this->assertInstanceOf(GraphNode::class, $node);
            $actualTailIds[] = $node->getId();
        }

        $this->assertEquals(['bar', 'baz'], $actualTailIds);
    }

    /**
     * @dataProvider flattenedArrowTailsIteratorProvider
     * @param array $input
     * @param array $expected
     * @throws \Deliveryman\Exception\LogicException
     */
    public function testFlattenedArrowTailsIterator(array $input, array $expected)
    {
        $builder = new GraphTreeBuilder();
        $collection = new GraphNodeCollection($builder->buildNodes($input));

        $actual = [];
        $actualKeys = [];
        foreach ($collection->flattenedArrows() as $arrow) {
            $actual[] = $arrow;
            $actualKeys[] = array_keys($arrow);
        }

        $expectedKeys = [];
        foreach ($expected as $arrow) {
            $expectedKeys[] = array_keys($arrow);
        }

        $this->assertEquals($expectedKeys, $actualKeys);
        $this->assertEquals($expected, $actual);
    }

    public function flattenedArrowTailsIteratorProvider()
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
            $nodes = [];
            foreach ($data['input'] as $datum) {
                $nodes[$datum['id']] = $input[] = new GraphNode($datum['id'], $datum['refs']);
            }

            $data['input'] = $input;

            $output = [];
            foreach ($data['output'] as $datum) {
                $outNodes = [];
                foreach ($datum as $id) {
                    $outNodes[$id] = $nodes[$id];
                }

                $output[] = $outNodes;
            }

            $data['output'] = $output;
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

        self::$fixtures = Yaml::parseFile(__DIR__ . '/../../Resources/fixtures/graph_node_collection.fixtures.yaml');

        return self::$fixtures[$name] ?? [];
    }
}