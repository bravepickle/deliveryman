<?php
/**
 * Date: 2018-12-14
 * Time: 22:15
 */

namespace DeliverymanTest\Channel;


use Deliveryman\Channel\HttpQueueChannel;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpResponse;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\HttpHeader;
use Deliveryman\Entity\ResponseItemInterface;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\TestCase;

class HttpQueueChannelTest extends TestCase
{
    /**
     * @dataProvider basicProvider
     * @param BatchRequest $input
     * @param $returnResponse
     * @param array $expected
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\ChannelException
     */
    public function testBasic(BatchRequest $input, $returnResponse, array $expected)
    {
        $handler = HandlerStack::create(new MockHandler([
            $returnResponse
        ]));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com',],
            'channels' => [
                'http_queue' => [
                    'request_options' => [
                        'handler' => $handler,
                    ],
                    'expected_status_codes' => [200, 404],
                ]
            ],
        ]);

        $channel = new HttpQueueChannel($configManager);
        $channel->send($input);

        $this->assertArrayHasKey('GET_http://example.com/comments', $channel->getOkResponses());

        /** @var HttpResponse $actualResponse */
        $actualResponse = $channel->getOkResponses()['GET_http://example.com/comments'];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

        $this->assertEquals($expected['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected['data'], $actualResponse->getData(), 'Body differs');
    }

    /**
     * @return array
     */
    public function basicProvider()
    {
        $queues = [
            [(new Request())
                ->setMethod('GET')
                ->setUri('http://example.com/comments')
                ->setHeaders([
                    (new HttpHeader())->setName('X-API')->setValue('test-server')
                ])
            ]
        ];

        $request = (new BatchRequest())->setQueues($queues);

        $expected = [
            'statusCode' => 404,
            'headers' => ['X-API' => ['test-server']],
            'data' => ['error' => 'Not Found!'],
        ];

        $response = new Response($expected['statusCode'], ['X-API' => 'test-server'], stream_for('{"error": "Not Found!"}'));

        return [
            [$request, $response, $expected],
        ];
    }

    /**
     * @dataProvider multiProvider
     * @param BatchRequest $input
     * @param array $returnResponses
     * @param array $expected
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\ChannelException
     */
    public function testMulti(BatchRequest $input, array $returnResponses, array $expected)
    {
        $handler = HandlerStack::create(new MockHandler($returnResponses));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com',],
            'channels' => [
                'http_queue' => [
                    'request_options' => [
                        'handler' => $handler,
                        'debug' => true,
                        'allow_redirects' => false,
                    ],
                    'expected_status_codes' => [200, 301, 400],
                ],
            ],
        ]);

        $channel = new HttpQueueChannel($configManager);
        $channel->send($input);
        $actualResponses = $channel->getOkResponses();

        $this->assertFalse($channel->hasErrors(), 'Errors found in processes.');
        $this->assertFalse($channel->hasFailedResponses(), 'Failed responses found in processes.');

        $this->assertArrayHasKey($expected[0]['id'], $channel->getOkResponses());

        /** @var HttpResponse $actualResponse */
        $actualResponse = $actualResponses[$expected[0]['id']];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

//        echo '<pre>';
//        print_r($expected[0]);
//        print_r($actualResponse);
//        die("\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");

        $this->assertEquals($expected[0]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[0]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[0]['data'], $actualResponse->getData(), 'Body differs');

        $this->assertArrayHasKey($expected[1]['id'], $actualResponses);

        /** @var HttpResponse $actualResponse */
        $actualResponse = $actualResponses[$expected[1]['id']];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

        $this->assertEquals($expected[1]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[1]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[1]['data'], $actualResponse->getData(), 'Body differs');

        $this->assertArrayHasKey($expected[2]['id'], $actualResponses);

        /** @var HttpResponse $actualResponse */
        $actualResponse = $actualResponses[$expected[2]['id']];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

        $this->assertEquals($expected[2]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[2]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[2]['data'], $actualResponse->getData(), 'Body differs');
    }

    /**
     * @return array
     */
    public function multiProvider()
    {
        $queues = [
            [
                (new Request())
                    ->setId('post_comments')
                    ->setUri('http://example.com/comments')
                    ->setData([
                        'text' => 'Nice job!',
                    ]),

                (new Request())
                    ->setId('head_comments')
                    ->setUri('http://example.com/comments/1')
                    ->setMethod('HEAD')
            ],
            [(new Request())
                ->setMethod('GET')
                ->setUri('http://example.com/users')
                ->setQuery(['uid' => 'zest'])
                ->setHeaders([
                    (new HttpHeader())->setName('X-API-Key')->setValue('test1'),
                    (new HttpHeader())->setName('X-API-Key')->setValue('test2')
                ])
            ],
        ];

        $request = (new BatchRequest())->setQueues($queues);

        $expected = [
            [
                'id' => 'post_comments',
                'statusCode' => 400,
                '_headers' => ['Content-Type' => ['plain/text; charset=utf8']],
                'headers' => [(new HttpHeader())->setName('Content-Type')->setValue('plain/text; charset=utf8')],
                'data' => 'Invalid input format',
            ],
            [
                'id' => 'GET_http://example.com/users',
                'statusCode' => 200,
                '_headers' => ['Content-Type' => ['application/json']],
                'headers' => [(new HttpHeader())->setName('Content-Type')->setValue('application/json')],
                'data' => ['success' => true],
                '_data' => '{"success":true}',
            ],
            [
                'id' => 'head_comments',
                'statusCode' => 301,
                '_headers' => ['Location' => ['http://www.example.com/comments/1']],
                'headers' => [(new HttpHeader())->setName('Location')->setValue('http://www.example.com/comments/1')],
                'data' => '',
            ],
        ];

        $responses = [
            new Response($expected[0]['statusCode'], $expected[0]['_headers'], stream_for($expected[0]['data'])),
            new Response($expected[1]['statusCode'], $expected[1]['_headers'], stream_for($expected[1]['_data'])),
            new Response($expected[2]['statusCode'], $expected[2]['_headers'], stream_for($expected[2]['data'])),
        ];

        return [
            [$request, $responses, $expected],
        ];
    }


    /**
     * @dataProvider singleQueue
     * @param BatchRequest $input
     * @param $returnResponses
     * @param array $expected
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\ChannelException
     */
    public function testSingleQueue(BatchRequest $input, array $returnResponses, array $expected)
    {
        $handler = HandlerStack::create(new MockHandler($returnResponses));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com',],
            'channels' => [
                'http_queue' => [
                    'request_options' => [
                        'handler' => $handler,
                        'debug' => false,
                        'allow_redirects' => false,
                    ],
                ]
            ],
            'on_fail' => 'proceed',
        ]);

        $channel = new HttpQueueChannel($configManager);
        $channel->send($input);
        $actualResponses = $channel->getOkResponses();

        $this->assertFalse($channel->hasErrors(), 'Errors found in processes.');

        $this->assertArrayHasKey($expected[0]['id'], $actualResponses);

        /** @var HttpResponse $actualResponse */
        $actualResponse = $actualResponses[$expected[0]['id']];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

        $this->assertEquals($expected[0]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[0]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[0]['data'], $actualResponse->getData(), 'Body differs');

        $this->assertArrayHasKey($expected[1]['id'], $actualResponses);

        /** @var HttpResponse $actualResponse */
        $actualResponse = $actualResponses[$expected[1]['id']];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

        $this->assertEquals($expected[1]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[1]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[1]['data'], $actualResponse->getData(), 'Body differs');

        $this->assertArrayHasKey($expected[2]['id'], $actualResponses);

        /** @var HttpResponse $actualResponse */
        $actualResponse = $actualResponses[$expected[2]['id']];

        $this->assertTrue($actualResponse instanceof ResponseItemInterface, 'Response should be ResponseItemInterface interface.');

        $this->assertEquals($expected[2]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[2]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[2]['data'], $actualResponse->getData(), 'Body differs');
    }

    /**
     * @return array
     */
    public function singleQueue()
    {
        $queues = [
            [
                (new Request())
                    ->setId('post_comments')
                    ->setUri('http://example.com/comments')
                    ->setData([
                        'text' => 'Nice job!',
                    ]),

                (new Request())
                    ->setMethod('GET')
                    ->setUri('http://example.com/users/2')
                    ->setQuery(['uid' => 'zest'])
                    ->setHeaders([
                        (new HttpHeader())->setName('X-API-Key')->setValue('test1'),
                        (new HttpHeader())->setName('X-API-Key')->setValue('test2')
                    ]),

                (new Request())
                    ->setId('head_comments')
                    ->setUri('http://example.com/comments/1')
                    ->setMethod('HEAD')
            ],
        ];

        $request = (new BatchRequest())->setQueues($queues);

        $expected = [
            [
                'id' => 'post_comments',
                'statusCode' => 400,
                '_headers' => ['Content-Type' => ['plain/text; utf8']],
                'headers' => [(new HttpHeader())->setName('Content-Type')->setValue('plain/text; utf8')],
                'data' => 'Invalid input format',
            ],
            [
                'id' => 'GET_http://example.com/users/2',
                'statusCode' => 200,
                '_headers' => ['Content-Type' => ['application/json']],
                'headers' => [(new HttpHeader())->setName('Content-Type')->setValue('application/json')],
                'data' => ['success' => true],
                '_data' => '{"success":true}',
            ],
            [
                'id' => 'head_comments',
                'statusCode' => 301,
                '_headers' => ['Location' => ['http://www.example.com/comments/1']],
                'headers' => [(new HttpHeader())->setName('Location')->setValue('http://www.example.com/comments/1')],
                'data' => '',
            ],
        ];

        $responses = [
            new Response($expected[0]['statusCode'], $expected[0]['_headers'], stream_for($expected[0]['data'])),
            new Response($expected[1]['statusCode'], $expected[1]['_headers'], stream_for($expected[1]['_data'])),
            new Response($expected[2]['statusCode'], $expected[2]['_headers'], stream_for($expected[2]['data'])),
        ];

        return [
            [$request, $responses, $expected],
        ];
    }

}