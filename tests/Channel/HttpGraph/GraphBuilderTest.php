<?php
/**
 * Date: 2018-12-22
 * Time: 22:36
 */

namespace DeliverymanTest\Channel\HttpGraph;


use Deliveryman\Channel\HttpGraph\GraphBuilder;
use Deliveryman\Channel\HttpGraph\GraphNode;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Exception\LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class GraphBuilderTest extends TestCase
{
    /**
     * Data sets for data provider taken from parsed files
     * @var array
     */
    protected static $fixtures;

    /**
     * @dataProvider buildNodesFromRequestsProvider
     * @param HttpRequest[] $requests
     * @param array $expected
     * @throws LogicException
     */
    public function testBuildNodesFromRequests($requests, array $expected)
    {
        $builder = new GraphBuilder();
        $items = $builder->buildNodesFromRequests($requests);

        // converting to simple array with same format as from data provider
        $actual = $this->convertNodesToArray($items);

        $this->assertEquals($expected, $actual);

    }

    public function buildNodesFromRequestsProvider()
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
                $input[] = (new HttpRequest())->setId($datum['id'])->setReq($datum['req'] ?? []);
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

        self::$fixtures = Yaml::parseFile(__DIR__ . '/../../Resources/fixtures/graph_builder.fixtures.yaml');

        return self::$fixtures[$name] ?? [];
    }

    /**
     * @throws LogicException
     */
    public function testBuildNodesFromRequestsValidationNonUnique()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Node IDs must be unique.');

        $requests = [
            (new HttpRequest())->setId('foo'),
            (new HttpRequest())->setId('foo'),
        ];

        $graphBuilder = new GraphBuilder();
        $graphBuilder->buildNodesFromRequests($requests);
    }

    /**
     * @throws LogicException
     */
    public function testBuildNodesFromRequestsValidationCircularRef()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('One or more nodes has circular references: foo.');

        $requests = [
            (new HttpRequest())->setId('foo')->setReq(['bar']),
            (new HttpRequest())->setId('bar')->setReq(['foo']),
        ];

        $graphBuilder = new GraphBuilder();
        $graphBuilder->buildNodesFromRequests($requests);
    }

    /**
     * @throws LogicException
     */
    public function testBuildNodesFromRequestsValidationReqUnknown()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown reference ID found: bar.');

        $requests = [
            (new HttpRequest())->setId('foo')->setReq(['bar']),
        ];

        $graphBuilder = new GraphBuilder();
        $graphBuilder->buildNodesFromRequests($requests);
    }

    /**
     * @param array|GraphNode[] $items
     * @return array
     * @throws LogicException
     */
    protected function convertNodesToArray(array $items): array
    {
        $actual = [];
        foreach ($items as $item) {
            $actual[$item->getId()] = ['successors' => [], 'predecessors' => []];

            foreach ($item->getSuccessors() as $successor) {
                $actual[$item->getId()]['successors'][] = $successor->getId();
            }

            foreach ($item->getPredecessors() as $predecessor) {
                $actual[$item->getId()]['predecessors'][] = $predecessor->getId();
            }
        }

        foreach ($actual as &$item) {
            sort($item['successors']);
            sort($item['predecessors']);
        }

        return $actual;
    }
}