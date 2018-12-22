<?php
/**
 * Date: 2018-12-22
 * Time: 18:28
 */

namespace DeliverymanTest\Channel;

use Deliveryman\Entity\BatchRequest;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Class HttpGraphChannel
 * @package DeliverymanTest\Channel
 */
class HttpGraphChannelTest extends TestCase
{
    /**
     * Data sets for data provider taken from parsed files
     * @var array
     */
    protected static $fixtures;

    /**
     * @dataProvider sendProvider
     * @param array $appConfig
     * @param BatchRequest $input
     * @param array|Response[] $responses
     * @param array|Request[] $sendRequests
     * @param array $expected
     */
    public function testSend(array $appConfig, BatchRequest $input, array $responses, array $sendRequests, array $expected)
    {
//        var_dump($appConfig);
//        var_dump($input);
//        die("\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");
    }


    public function sendProvider()
    {
        return $this->prepareProviderData(self::getFixtures(__FUNCTION__));
    }

    /**
     * @param array $data
     * @return array
     */
    protected function prepareProviderData(array $data): array
    {
        foreach ($data as $key => $datum) {
            $data[$key]['input'] = (new BatchRequest())
                ->setConfig($datum['input']['config'] ?? null)
                ->setData($datum['input']['data'])
            ;

            foreach ($datum['responses'] as $subKey => $response) {
                $data[$key]['responses'][$subKey] = new Response(
                    $response['statusCode'] ?? null,
                    $response['headers'] ?? null,
                    $response['data'] ?? null
                );
            }

            foreach ($datum['sentRequests'] as $subKey => $request) {
                $data[$key]['sentRequests'][$subKey] = new Request(
                    $request['method'] ?? null,
                    $request['uri'] ?? null,
                    $request['headers'] ?? null,
                    $request['data'] ?? null
                );
            }
        }

        return $data;
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

        self::$fixtures = Yaml::parseFile(__DIR__ . '/../Resources/fixtures/channel.http_graph.fixtures.yaml');

        return self::$fixtures[$name] ?? [];
    }
}