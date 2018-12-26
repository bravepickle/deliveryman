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
use Deliveryman\Entity\HttpQueue\ChannelConfig;
use Deliveryman\Entity\HttpResponse;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\EventListener\BuildResponseEvent;
use Deliveryman\Exception\BaseException;
use Deliveryman\Exception\ChannelException;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
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
     * @throws \Deliveryman\Exception\InvalidArgumentException
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

    /**
     * @return array
     */
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
            $this->initInput($data, $datum, $key);
            $this->initResponses($data, $datum, $key);
            $this->initRequests($data, $datum, $key);
            $this->initOkResponses($data, $datum, $key);
            $this->initFailedResponses($data, $datum, $key);
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
     * @throws \Deliveryman\Exception\InvalidArgumentException
     */
    public function testSendNoRequests()
    {
        $this->expectExceptionMessage(ChannelException::class);
        $this->expectExceptionMessage('Requests must be defined.');

        $batch = new BatchRequest();
        $configManager = new ConfigManager();
        $configManager->addConfiguration(['domains' => ['localhost']]);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($batch);
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailSingle()
    {
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
            ]);
        $appConfig = [
            'domains' => ['localhost'],
            'on_fail' => 'proceed',
        ];
        $expected = [
            'ok' => [],
            'failed' => [
                'home' => (new HttpResponse())->setId('home')->setStatusCode(400)->setHeaders([])
            ],
            'errors' => ['home' => 'Request failed to complete.'],
        ];

        $response = new Response(400);

        $handler = HandlerStack::create(function ($request) use ($response) {
            throw new RequestException('Expected exception found.', $request, $response);
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

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailReject()
    {
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
                    ->setConfig((new RequestConfig())->setOnFail('proceed')),
                (new HttpRequest())->setId('logout')->setUri('localhost/logout')->setReq(['home'])
                    ->setConfig((new RequestConfig())->setOnFail('proceed')),
                (new HttpRequest())->setId('login')->setUri('localhost/login')->setReq(['logout'])
                    ->setConfig((new RequestConfig())->setOnFail('abort-queue')),
                (new HttpRequest())->setId('feedback')->setUri('localhost/feedback')->setReq(['home'])
                    ->setConfig((new RequestConfig())->setOnFail('proceed')),
                (new HttpRequest())->setId('profile')->setUri('localhost/profile')->setReq(['feedback']),
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $response = new Response(400);

        $handler = HandlerStack::create(function ($request) use ($response) {
            throw new RequestException('Expected exception found.', $request, $response);
        });

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);

        $this->assertEquals([], $channel->getOkResponses(), 'Success responses differ');
        $this->assertEquals(['home', 'logout', 'feedback', 'login', 'profile',], array_keys($channel->getFailedResponses()), 'Failed responses differ');
        $this->assertEquals(['home', 'logout', 'feedback', 'login', 'profile',], array_keys($channel->getErrors()), 'Error responses differ');
    }


    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailUnexpectedOnFail()
    {
        $this->expectExceptionMessage('Unexpected fail handler type: unexpected_case');
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
                    ->setConfig((new RequestConfig())->setOnFail('unexpected_case')),
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $response = new Response(400);

        $handler = HandlerStack::create(function ($request) use ($response) {
            throw new RequestException('Expected exception found.', $request, $response);
        });

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailRejectAbort()
    {
        $this->expectExceptionMessage('Queue terminated due to request errors.');
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $response = new Response(400);

        $handler = HandlerStack::create(function ($request) use ($response) {
            throw new RequestException('Expected exception found.', $request, $response);
        });

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendAbortUnexpectedResponse()
    {
        $this->expectExceptionMessage('Queue terminated due to request errors.');
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $response = new Response(400);

        $handler = HandlerStack::create(function () use ($response) {
            return \GuzzleHttp\Promise\promise_for($response);
        });

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailAbortChain()
    {
        $this->expectExceptionMessage('Queue terminated due to request errors.');
        $handler = new MockHandler([
            new Response(400),
        ]);
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/'),
                (new HttpRequest())->setId('login')->setUri('localhost/login')->setReq(['home']),
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $handler = HandlerStack::create($handler);

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailUnknownDataFormat()
    {
        $this->expectExceptionMessage('Not supported data format: binary');
        $handler = new MockHandler([
            new Response(400),
        ]);
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
                    ->setConfig((new RequestConfig())->setFormat('binary')),
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $handler = HandlerStack::create($handler);

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendFailUnknownOnFail()
    {
        $this->expectExceptionMessage('Unexpected fail handler type: crash');
        $handler = new MockHandler([
            new BaseException('Good bad news'),
            new Response(400),
        ]);
        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/')
                    ->setConfig((new RequestConfig())->setOnFail('crash')),
                (new HttpRequest())->setId('foo')->setUri('localhost/foo')->setReq(['home']),
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $handler = HandlerStack::create($handler);

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager);
        $channel->send($input);

//        print_r($channel->getFailedResponses());
//        print_r($channel->getErrors());
    }

    /**
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendWithEventDispatcher()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('deliveryman.response.post_build', function ($event, $action) {
            $this->assertInstanceOf(BuildResponseEvent::class, $event);
            $this->assertEquals('deliveryman.response.post_build', $action);
        });

        $handler = new MockHandler([
            new Response(200),
        ]);

        $input = (new BatchRequest())
            ->setData([
                (new HttpRequest())->setId('home')->setUri('localhost/'),
            ]);
        $appConfig = [
            'domains' => ['localhost'],
        ];

        $handler = HandlerStack::create($handler);

        $appConfig['channels']['http_graph']['request_options']['handler'] = $handler;

        $configManager = new ConfigManager();
        $configManager->addConfiguration($appConfig);

        $channel = new HttpGraphChannel($configManager, null, $dispatcher);
        $channel->send($input);
    }

    /**
     * @dataProvider clientRequestProvider
     * @param array $appConfig
     * @param \Symfony\Component\HttpFoundation\Request $clientRequest
     * @param BatchRequest $input
     * @param array $responses
     * @param array $expectedRequests
     * @param array $expected
     * @throws ChannelException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSendClientRequest(
        array $appConfig,
        ?\Symfony\Component\HttpFoundation\Request $clientRequest,
        BatchRequest $input,
        array $responses,
        array $expectedRequests,
        array $expected
    )
    {
        $mockHandler = new MockHandler($responses);

        $handler = HandlerStack::create(function (RequestInterface $request, $options) use ($mockHandler, $expectedRequests) {
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

        $requestStack = new RequestStack();
        if ($clientRequest) {
            $requestStack->push($clientRequest);
        }
        $channel = new HttpGraphChannel($configManager, $requestStack);
        $channel->send($input);

        $this->assertEquals($expected['ok'], $channel->getOkResponses(), 'Success responses differ');
        $this->assertEquals($expected['failed'], $channel->getFailedResponses(), 'Failed responses differ');
        $this->assertEquals($expected['errors'], $channel->getErrors(), 'Error responses differ');
    }

    /**
     * @return array
     */
    public function clientRequestProvider()
    {
        return $this->prepareClientRequestProviderData(self::getFixtures(__FUNCTION__));
    }

    /**
     * @param array $data
     * @return array
     */
    protected function prepareClientRequestProviderData(array $data): array
    {
        foreach ($data as $key => $datum) {
            $this->initInput($data, $datum, $key);
            $this->initResponses($data, $datum, $key);
            $this->initRequests($data, $datum, $key);
            $this->initOkResponses($data, $datum, $key);
            $this->initFailedResponses($data, $datum, $key);
            $this->initClientRequest($data, $datum, $key);
        }

        return $data;
    }

    /**
     * @param array $data
     * @param $datum
     * @param $key
     */
    protected function initInput(array &$data, &$datum, $key): void
    {
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
                    if (!empty($req['config']['channel'])) {
                        $req['config']['channel'] = (new ChannelConfig())
                            ->setExpectedStatusCodes($req['config']['channel']['expected_status_codes'] ?? null);
                    }

                    $req['config'] = (new RequestConfig())
                        ->setConfigMerge($req['config']['config_merge'] ?? null)
                        ->setOnFail($req['config']['on_fail'] ?? null)
                        ->setSilent($req['config']['silent'] ?? null)
                        ->setFormat($req['config']['format'] ?? null)
                        ->setChannel($req['config']['channel'] ?? null);
                }

                $body[] = (new HttpRequest())
                    ->setConfig($req['config'] ?? null)
                    ->setId($req['id'] ?? null)
                    ->setHeaders($req['headers'] ?? null)
                    ->setMethod($req['method'] ?? null)
                    ->setUri($req['uri'] ?? null)
                    ->setQuery($req['query'] ?? null)
                    ->setData($req['data'] ?? null)
                    ->setReq($req['req'] ?? []);
            }
        }

        if (!empty($datum['input']['config'])) {
            if (!empty($datum['input']['config']['channel'])) {
                $datum['input']['config']['channel'] = (new ChannelConfig())
                    ->setExpectedStatusCodes(
                        $datum['input']['config']['channel']['expected_status_codes'] ?? null);
            }

            $datum['input']['config'] = (new RequestConfig())
                ->setConfigMerge($datum['input']['config']['config_merge'] ?? null)
                ->setOnFail($datum['input']['config']['on_fail'] ?? null)
                ->setSilent($datum['input']['config']['silent'] ?? null)
                ->setFormat($datum['input']['config']['format'] ?? null)
                ->setChannel($datum['input']['config']['channel'] ?? null);
        }

        $data[$key]['input'] = (new BatchRequest())
            ->setConfig($datum['input']['config'] ?? null)
            ->setData($body);
    }

    /**
     * @param array $data
     * @param $datum
     * @param $key
     */
    protected function initResponses(array &$data, $datum, $key): void
    {
        foreach ($datum['responses'] as $subKey => $response) {
            $data[$key]['responses'][$subKey] = new Response(
                $response['statusCode'] ?? null,
                $response['headers'] ?? [],
                $response['data'] ?? null
            );
        }
    }

    /**
     * @param array $data
     * @param $datum
     * @param $key
     */
    protected function initRequests(array &$data, $datum, $key): void
    {
        foreach ($datum['sentRequests'] as $subKey => $request) {
            $data[$key]['sentRequests'][$subKey] = new Request(
                $request['method'] ?? null,
                $request['uri'] ?? null,
                $request['headers'] ?? [],
                $request['data'] ?? null
            );
        }
    }

    /**
     * @param array $data
     * @param $datum
     * @param $key
     */
    protected function initOkResponses(array &$data, &$datum, $key): void
    {
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
                    ->setData($resp['data'] ?? null);
            }
            $data[$key]['output']['ok'] = $body;
        }
    }

    /**
     * @param array $data
     * @param $datum
     * @param $key
     */
    protected function initFailedResponses(array &$data, &$datum, $key): void
    {
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
                    ->setData($resp['data'] ?? null);
            }
            $data[$key]['output']['failed'] = $body;
        }
    }

    /**
     * @param array $data
     * @param $datum
     * @param $key
     */
    protected function initClientRequest(array &$data, $datum, $key): void
    {
        if (!empty($datum['client'])) {
            $request = new \Symfony\Component\HttpFoundation\Request();
            if (!empty($datum['client']['headers'])) {
                foreach ($datum['client']['headers'] as $header => $values) {
                    $request->headers->set($header, $values);
                }
            }

            $data[$key]['client'] = $request;
        }

    }
}