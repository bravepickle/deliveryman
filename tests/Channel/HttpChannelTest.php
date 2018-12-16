<?php
/**
 * Date: 2018-12-14
 * Time: 22:15
 */

namespace DeliverymanTest\Channel;


use Deliveryman\Channel\HttpChannel;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class HttpChannelTest extends TestCase
{
    /**
     * @dataProvider basicProvider
     * @param array $input
     * @param $returnResponse
     * @param array $expected
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\ChannelException
     */
    public function testBasic(array $input, $returnResponse, array $expected)
    {
        $handler = HandlerStack::create(new MockHandler([
            $returnResponse
        ]));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com',],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'handler' => $handler,
                    ],
                ]
            ]
        ]);

        $channel = new HttpChannel($configManager);
        $actualResponses = $channel->send($input);

        $this->assertArrayHasKey('GET_http://example.com/comments', $actualResponses);

        /** @var Response $actualResponse */
        $actualResponse = $actualResponses['GET_http://example.com/comments'];

        $this->assertTrue($actualResponse instanceof ResponseInterface, 'Response should be PSR-7 interface.');

        $this->assertEquals($expected['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected['data'], $actualResponse->getBody()->getContents(), 'Body differs');
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
                    (new RequestHeader())->setName('X-API')->setValue('test-server')
                ])
            ]
        ];

        $expected = [
            'statusCode' => 404,
            'headers' => ['X-API' => ['test-server']],
            'data' => '{"error": "Not Found!"}',
        ];

        $response = new Response($expected['statusCode'], ['X-API' => 'test-server'], stream_for('{"error": "Not Found!"}'));

        return [
            [$queues, $response, $expected],
        ];
    }

    /**
     * @dataProvider multiProvider
     * @param array $input
     * @param $returnResponses
     * @param array $expected
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\ChannelException
     */
    public function testMulti(array $input, array $returnResponses, array $expected)
    {
        $handler = HandlerStack::create(new MockHandler($returnResponses));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com',],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'handler' => $handler,
                        'debug' => true,
                        'allow_redirects' => false,
                    ],
                ]
            ]
        ]);

        $channel = new HttpChannel($configManager);
        $actualResponses = $channel->send($input);

        $this->assertFalse($channel->hasErrors(), 'Errors found in processes.');

        $this->assertArrayHasKey($expected[0]['id'], $actualResponses);

        /** @var Response $actualResponse */
        $actualResponse = $actualResponses[$expected[0]['id']];

        $this->assertTrue($actualResponse instanceof ResponseInterface, 'Response should be PSR-7 interface.');


        $this->assertEquals($expected[0]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[0]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[0]['data'], $actualResponse->getBody()->getContents(), 'Body differs');

        $this->assertArrayHasKey($expected[1]['id'], $actualResponses);

        /** @var Response $actualResponse */
        $actualResponse = $actualResponses[$expected[1]['id']];

        $this->assertTrue($actualResponse instanceof ResponseInterface, 'Response should be PSR-7 interface.');

        $this->assertEquals($expected[1]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[1]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[1]['data'], $actualResponse->getBody()->getContents(), 'Body differs');

        $this->assertArrayHasKey($expected[2]['id'], $actualResponses);

        /** @var Response $actualResponse */
        $actualResponse = $actualResponses[$expected[2]['id']];

        $this->assertTrue($actualResponse instanceof ResponseInterface, 'Response should be PSR-7 interface.');

        $this->assertEquals($expected[2]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
        $this->assertEquals($expected[2]['headers'], $actualResponse->getHeaders(), 'Headers differ');
        $this->assertEquals($expected[2]['data'], $actualResponse->getBody()->getContents(), 'Body differs');
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
                    (new RequestHeader())->setName('X-API-Key')->setValue('test1'),
                    (new RequestHeader())->setName('X-API-Key')->setValue('test2')
                ])
            ],
        ];

        $expected = [
            [
                'id' => 'post_comments',
                'statusCode' => 400,
                'headers' => ['Content-Type' => ['plain/text; utf8']],
                'data' => 'Invalid input format',
            ],
            [
                'id' => 'GET_http://example.com/users',
                'statusCode' => 200,
                'headers' => ['Content-Type' => ['application/json']],
                'data' => '{"success": true}',
            ],
            [
                'id' => 'head_comments',
                'statusCode' => 301,
                'headers' => ['Location' => ['http://www.example.com/comments/1']],
                'data' => '',
            ],
        ];

        $responses = [
            new Response($expected[0]['statusCode'], $expected[0]['headers'], stream_for($expected[0]['data'])),
            new Response($expected[1]['statusCode'], $expected[1]['headers'], stream_for($expected[1]['data'])),
            new Response($expected[2]['statusCode'], $expected[2]['headers'], stream_for($expected[2]['data'])),
        ];

        return [
            [$queues, $responses, $expected],
        ];
    }

    public function testConcurrency()
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], stream_for('first')),
            new Response(201, [], stream_for('second')),
            new Response(202, [], stream_for('third!')),
        ]));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com',],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'handler' => $handler,
                        'debug' => true,
                    ],
                ]
            ]
        ]);

        $input = [
            [
                (new Request())
                    ->setId('post_comments')
                    ->setUri('http://example.com/comments'),

                (new Request())
                    ->setId('head_comments')
                    ->setUri('http://example.com/comments/1')
                    ->setMethod('HEAD')
            ],
            [(new Request())
                ->setMethod('GET')
                ->setUri('http://example.com/users')
            ],
        ];


        $channel = new HttpChannel($configManager);
        $actualResponses = $channel->send($input);

//        print_r($expected);
//        print_r($actualResponses);
        print_r(array_keys($actualResponses));

        die("\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");

        $this->assertNotEmpty($actualResponses);
        $this->assertTrue(count($actualResponses) == 3);
//        $this->assertFalse($channel->hasErrors(), 'Errors found in processes.');
//
//        $this->assertArrayHasKey($expected[0]['id'], $actualResponses);
//
//        /** @var Response $actualResponse */
//        $actualResponse = $actualResponses[$expected[0]['id']];
//
//        $this->assertTrue($actualResponse instanceof ResponseInterface, 'Response should be PSR-7 interface.');
//
//
//        $this->assertEquals($expected[0]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
//        $this->assertEquals($expected[0]['headers'], $actualResponse->getHeaders(), 'Headers differ');
//        $this->assertEquals($expected[0]['data'], $actualResponse->getBody()->getContents(), 'Body differs');
//
//        $this->assertArrayHasKey($expected[1]['id'], $actualResponses);
//
//        /** @var Response $actualResponse */
//        $actualResponse = $actualResponses[$expected[1]['id']];
//
//        $this->assertTrue($actualResponse instanceof ResponseInterface, 'Response should be PSR-7 interface.');
//
//        $this->assertEquals($expected[1]['statusCode'], $actualResponse->getStatusCode(), 'Status code differ');
//        $this->assertEquals($expected[1]['headers'], $actualResponse->getHeaders(), 'Headers differ');
//        $this->assertEquals($expected[1]['data'], $actualResponse->getBody()->getContents(), 'Body differs');

    }
}