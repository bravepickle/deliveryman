<?php
/**
 * Date: 2018-12-22
 * Time: 18:28
 */

namespace DeliverymanTest\Channel;

use Deliveryman\Channel\HttpGraphChannel;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\HttpHeader;
use Deliveryman\Entity\HttpResponse;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Exception\ChannelException;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
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
     * @param array $expectedRequests
     * @param array $expected
     * @throws \Deliveryman\Exception\ChannelException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     */
    public function testSend(array $appConfig, BatchRequest $input, array $responses, array $expectedRequests, array $expected)
    {
        $mockHandler = new MockHandler($responses);
        $handler = HandlerStack::create(function (
            RequestInterface $request,
            array $options
        ) use ($mockHandler, &$expectedRequests) {
            $expected = array_shift($expectedRequests);

            $this->assertEquals($expected->getMethod(), $request->getMethod(), 'Sent request method differs from expected.');
            $this->assertEquals($expected->getUri(), $request->getUri(), 'Sent request URI differs from expected.');
            $this->assertEquals($expected->getHeaders(), $request->getHeaders(), 'Sent request headers differs from expected.');
            $this->assertEquals($expected->getBody()->getContents(), $request->getBody()->getContents(), 'Sent request body differs from expected.');

            return $mockHandler($request, $options);
        });

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);

        $this->assertEquals($expected['ok'], $channel->getOkResponses(), 'Success responses differ');
        $this->assertEquals($expected['failed'], $channel->getFailedResponses(), 'Failed responses differ');
        $this->assertEquals($expected['errors'], $channel->getErrors(), 'Error responses differ');
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
            if (empty($datum['input']['data'])) {
                $body = $datum['input']['data'];
            } else {
                $body = [];
                foreach ($datum['input']['data'] as $req) {
                    if (!empty($req['headers'])) {
                        $headers = [];
                        foreach ($req['headers'] as $header) {
                            $headers[] = (new HttpHeader())
                                ->setName($header['name'])
                                ->setValue($header['value']);
                        }
                        $req['headers'] = $headers;
                    }

                    if (!empty($req['config'])) {
                        $req['config'] = (new RequestConfig())
                            ->setConfigMerge($req['config']['config_merge'] ?? null)
                            ->setOnFail($req['config']['on_fail'] ?? null)
                            ->setSilent($req['config']['silent'] ?? null)
                            ->setFormat($req['config']['format'] ?? null)
                        ;
                    }

                    $body[] = (new HttpRequest())
                        ->setConfig($req['config'] ?? null)
                        ->setId($req['id'] ?? null)
                        ->setHeaders($req['headers'] ?? null)
                        ->setMethod($req['method'] ?? null)
                        ->setUri($req['uri'] ?? null)
                        ->setQuery($req['query'] ?? null)
                        ->setData($req['data'] ?? null)
                        ->setReq($req['req'] ?? [])
                    ;
                }
            }

            if (!empty($datum['input']['config'])) {
                $datum['input']['config'] = (new RequestConfig())
                    ->setConfigMerge($datum['input']['config']['config_merge'] ?? null)
                    ->setOnFail($datum['input']['config']['on_fail'] ?? null)
                    ->setSilent($datum['input']['config']['silent'] ?? null)
                    ->setFormat($datum['input']['config']['format'] ?? null)
                ;
            }

            $data[$key]['input'] = (new BatchRequest())
                ->setConfig($datum['input']['config'] ?? null)
                ->setData($body)
            ;

            foreach ($datum['responses'] as $subKey => $response) {
                $data[$key]['responses'][$subKey] = new Response(
                    $response['statusCode'] ?? null,
                    $response['headers'] ?? [],
                    $response['data'] ?? null
                );
            }

            foreach ($datum['sentRequests'] as $subKey => $request) {
                $data[$key]['sentRequests'][$subKey] = new Request(
                    $request['method'] ?? null,
                    $request['uri'] ?? null,
                    $request['headers'] ?? [],
                    $request['data'] ?? null
                );
            }

            if (!empty($datum['output']['ok'])) {
                $body = [];
                foreach ($datum['output']['ok'] as $id => $resp) {
                    if (!empty($resp['headers'])) {
                        $headers = [];
                        foreach ($resp['headers'] as $header) {
                            $headers[] = (new HttpHeader())
                                ->setName($header['name'])
                                ->setValue($header['value']);
                        }
                        $resp['headers'] = $headers;
                    }

                    $body[$id] = (new HttpResponse())
                        ->setId($resp['id'] ?? null)
                        ->setHeaders($resp['headers'] ?? null)
                        ->setStatusCode($resp['statusCode'] ?? null)
                        ->setData($resp['data'] ?? null)
                    ;
                }
                $data[$key]['output']['ok'] = $body;
            }

            if (!empty($datum['output']['failed'])) {
                $body = [];
                foreach ($datum['output']['failed'] as $id => $resp) {
                    if (!empty($resp['headers'])) {
                        $headers = [];
                        foreach ($resp['headers'] as $header) {
                            $headers[] = (new HttpHeader())
                                ->setName($header['name'])
                                ->setValue($header['value']);
                        }
                        $resp['headers'] = $headers;
                    }

                    $body[$id] = (new HttpResponse())
                        ->setId($resp['id'] ?? null)
                        ->setHeaders($resp['headers'] ?? null)
                        ->setStatusCode($resp['statusCode'] ?? null)
                        ->setData($resp['data'] ?? null)
                    ;
                }
                $data[$key]['output']['failed'] = $body;
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

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendNoRequests()
    {
        $this->expectExceptionMessage(ChannelException::class);
        $this->expectExceptionMessage('Requests must be defined.');

        $batch = new BatchRequest();
        $configManager = new ConfigManager();

        $channel = new HttpGraphChannel($configManager);
        $channel->send($batch);
    }
}