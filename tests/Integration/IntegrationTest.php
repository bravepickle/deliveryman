<?php
/**
 * Date: 2018-12-16
 * Time: 23:11
 */

namespace DeliverymanTest\Integration;

use Deliveryman\Channel\HttpChannel;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Normalizer\BatchRequestNormalizer;
use Deliveryman\Service\BatchRequestValidator;
use Deliveryman\Service\ConfigManager;
use Deliveryman\Service\Sender;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Class IntegrationTest
 * @package DeliverymanTest\Integration
 */
class IntegrationTest extends TestCase
{
    /**
     * @return Serializer
     */
    protected function initSerializer()
    {
        $classMetadataFactory = new ClassMetadataFactory(
            new YamlFileLoader(__DIR__ . '/../../src/Resources/serialization.yaml')
        );
        $getSetNormalizer = new GetSetMethodNormalizer($classMetadataFactory);
        $batchNormalizer = new BatchRequestNormalizer();

        $serializer = new Serializer([$batchNormalizer, $getSetNormalizer], [new JsonEncoder()]);

        return $serializer;
    }

    /**
     * @param array $config
     * @return Sender
     */
    protected function initSender(array $config): Sender
    {
        $configManager = new ConfigManager();
        $configManager->addConfiguration($config);

        return new Sender(new HttpChannel($configManager), $configManager, new BatchRequestValidator($configManager));
    }

    /**
     * @ dataProvider batchRequestProvider
     * @dataProvider configProvider
     * @param array $config
     * @param array $input
     * @param array $responses
     * @param RequestInterface[]|array $expectedRequests
     * @param array $output
     * @throws \Deliveryman\Exception\SendingException
     * @throws \Deliveryman\Exception\SerializationException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testBatchRequest(
        array $config,
        array $input,
        array $responses,
        array $expectedRequests,
        array $output
    ) {
        $mockHandler = new MockHandler($responses);
        $config['channels']['http']['request_options']['handler'] = HandlerStack::create(function (
            RequestInterface $request,
            array $options
        ) use ($mockHandler, &$expectedRequests) {
            $expected = array_shift($expectedRequests);
//            var_dump($expected);
//            var_dump((string)$request->getBody());
//
//            die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");

            $this->assertEquals($expected, $request, 'Sent request data differs from expected.');

            return $mockHandler($request, $options);
        });
        $sender = $this->initSender($config);
        $serializer = $this->initSerializer();

        $this->assertTrue($serializer->supportsDenormalization($input, BatchRequest::class));

        /** @var BatchRequest $batchRequest */
        $batchRequest = $serializer->denormalize($input, BatchRequest::class);
        $batchResponse = $sender->send($batchRequest);

        $this->assertTrue($serializer->supportsNormalization($batchResponse, 'json'));
        $actual = $serializer->normalize($batchResponse);

        $this->assertEquals($output, $actual);
    }

    /**
     * Requests testing
     * @return array
     */
    public function batchRequestProvider()
    {
        return [
            [
                'config' => [
                    'domains' => ['http://example.com',],
                    'channels' => [
                        'http' => [
                            'request_options' => [
                                'debug' => false,
                                'allow_redirects' => false,
                            ],
                        ],
                    ],
                ],

                'input' => [
                    'queues' => [
                        ['uri' => 'http://example.com/ask-something', 'id' => 'ask-something'],
                        [['uri' => 'http://example.com/do-something', 'method' => 'PUT']],
                    ],
                ],

                'responses' => [
                    new Response(200, ['Content-Type' => ['application/json']], stream_for('{"success":true}')),
                    new Response(204, ['Content-Type' => ['application/json']]),
                ],

                'output' => [
                    'data' => [
                        'ask-something' => [
                            'id' => 'ask-something',
                            'headers' => [
                                [
                                    'name' => 'Content-Type',
                                    'value' => 'application/json',
                                ],
                            ],
                            'statusCode' => 200,
                            'data' => [
                                'success' => true,
                            ],
                        ],

                        'PUT_http://example.com/do-something' => [
                            'id' => 'PUT_http://example.com/do-something',
                            'headers' => [
                                [
                                    'name' => 'Content-Type',
                                    'value' => 'application/json',
                                ],
                            ],
                            'statusCode' => 204,
                            'data' => null,
                        ],
                    ],
                    'status' => 'ok',
                    'errors' => null,
                ],
            ],
        ];
    }

    /**
     * Configs usage testing in requests
     * @return array
     */
    public function configProvider()
    {
        return [
            [ // silent output
                'config' => [
                    'domains' => ['http://example.com',],
                    'silent' => true,
                    'channels' => [
                        'http' => [
                            'request_options' => [
                                'headers' => [
                                    'User-Agent' => ['testing/1.0'],
                                ],
                            ]
                        ]
                    ]
                ],

                'input' => [
                    'queues' => [
                        ['uri' => 'http://example.com/ask-something', 'id' => '#45'],
                    ],
                ],

                'responses' => [
                    new Response(200, ['Content-Type' => ['application/json']], stream_for('{"success":true}')),
                ],

                'sentRequests' => [
                    new Request('GET', 'http://example.com/ask-something', ['User-Agent' => ['testing/1.0'],]),
                ],

                'output' => [
                    'data' => null,
                    'status' => 'ok',
                    'errors' => null,
                ],
            ],
//            [ // silent output with errors
//                'config' => [
//                    'domains' => ['http://example.com',],
//                    'silent' => true,
//                ],
//
//                'input' => [
//                    'queues' => [
//                        ['uri' => 'http://example.com/ask-something', 'id' => '#45'],
//                        ['uri' => 'http://example.com/ask-something', 'id' => '#46'],
//                    ],
//                ],
//
//                'responses' => [
//                    new Response(200, ['Content-Type' => ['application/json']], stream_for('{"success":true}')),
//                    new Response(500, ['Content-Type' => ['application/json']], stream_for('{"err":"server error"}')),
//                ],
//
//                'output' => [
//                    'data' => null,
//                    'status' => 'failed',
//                    'errors' => [
//                        '#46' => [
//                            'id' => '#46',
//                            'headers' => [
//                                [
//                                    'name' => 'Content-Type',
//                                    'value' => 'application/json',
//                                ],
//                            ],
//                            'statusCode' => 500,
//                            'data' => [
//                                'err' => 'server error',
//                            ],
//                        ],
//                    ],
//                ],
//            ],
//            [ // partial silent output on request level
//                'config' => [
//                    'domains' => ['http://example.com',],
//                    'silent' => false,
//                ],
//
//                'input' => [
//                    'queues' => [
//                        [
//                            ['uri' => 'http://example.com/foo', 'id' => '#45', 'config' => ['silent' => true]],
//                            ['uri' => 'http://example.com/bar', 'id' => '#46'],
//                        ],
//                    ],
//                ],
//
//                'responses' => [
//                    new Response(200, ['Content-Type' => ['application/json']], stream_for('{"success":true}')),
//                    new Response(200, ['X-API' => ['123']], stream_for('{"data":"ok"}')),
//                ],
//
//                'output' => [
//                    'errors' => null,
//                    'status' => 'ok',
//                    'data' => [
//                        '#46' => [
//                            'id' => '#46',
//                            'headers' => [
//                                [
//                                    'name' => 'X-API',
//                                    'value' => '123',
//                                ],
//                            ],
//                            'statusCode' => 200,
//                            'data' => [
//                                'data' => 'ok',
//                            ],
//                        ],
//                    ],
//                ],
//            ],
        ];
    }

}