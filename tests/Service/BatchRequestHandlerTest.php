<?php

namespace DeliverymanTest\Service;


use Deliveryman\Channel\HttpGraphChannel;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\HttpGraph\HttpHeader;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Service\BatchRequestValidator;
use Deliveryman\Service\ConfigManager;
use Deliveryman\Service\BatchRequestHandler;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\TestCase;

class BatchRequestHandlerTest extends TestCase
{
    /**
     * Sending batch requests
     * @dataProvider sendProvider
     * @param BatchRequest $input
     * @param array $responses
     * @param BatchResponse $expected
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSend(BatchRequest $input, array $responses, BatchResponse $expected)
    {
        $config = [];
        $config['channels']['http_graph']['domains'] = ['example.com', 'http://foo.com'];
        $config['channels']['http_graph']['request_options']['handler'] = HandlerStack::create(new MockHandler($responses));

        $configManager = new ConfigManager();
        $configManager->addConfiguration($config);

        $provider = new HttpGraphChannel($configManager);

        $sender = new BatchRequestHandler($provider, $configManager, new BatchRequestValidator($configManager));
        $actual = $sender($input);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function sendProvider()
    {
        $reqId = 'test_get';
        $batchRequest = new BatchRequest();
        $batchRequest->setData([
            (new HttpRequest())->setId($reqId)->setUri('http://example.com'),
        ]);

        $returnedResponses[$reqId] = (new \Deliveryman\Entity\HttpResponse())
            ->setId($reqId)
            ->setHeaders([new HttpHeader('Content-Type', 'application/json')])
            ->setStatusCode(200)
            ->setData(['server' => 'success!'])
        ;

        $batchResponse = new BatchResponse();
        $batchResponse->setStatus('ok');
        $batchResponse->setData($returnedResponses);

        $responses = [];
        $responses[$reqId] = (new Response())
            ->withHeader('Content-Type', 'application/json')
            ->withBody(stream_for('{"server": "success!"}'));

        return [
            [
                'input' => $batchRequest,
                'responses' => $responses,
                'expect' => $batchResponse,
            ],
        ];
    }
}