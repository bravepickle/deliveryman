<?php
/**
 * Date: 2018-12-14
 * Time: 22:15
 */

namespace DeliverymanTest\ClientProvider;


use Deliveryman\ClientProvider\HttpClientProvider;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class HttpClientProviderTest extends TestCase
{
    /**
     * @dataProvider basicProvider
     * @param array $input
     * @param $returnResponse
     * @param array $expected
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testBasic(array $input, $returnResponse, array $expected)
    {
        $handler = HandlerStack::create(new MockHandler([
            $returnResponse
        ]));

        $configManager = new ConfigManager();
        $configManager->addConfiguration([
            'domains' => ['http://example.com', ],
            'providers' => [
                'http' => [
                    'request_options' => [
                        'handler' => $handler,
                        'http_errors' => false,
                    ],
                ]
            ]
        ]);

        $clientProvider = new HttpClientProvider($configManager);
        $actualResponses = $clientProvider->send($input);

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
}