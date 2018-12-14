<?php

namespace DeliverymanTest\Service;


use Deliveryman\ClientProvider\HttpClientProvider;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\Request;
use Deliveryman\Service\BatchRequestValidator;
use Deliveryman\Service\ConfigManager;
use Deliveryman\Service\Sender;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\TestCase;

class SenderTest extends TestCase
{
    /**
     * Sending batch requests
     * @dataProvider sendProvider
     * @param BatchRequest $input
     * @param array $responses
     * @param BatchResponse $expect
     * @throws \Deliveryman\Exception\SendingException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testSend(BatchRequest $input, array $responses, BatchResponse $expect)
    {
        $configManager = new ConfigManager();
        $configManager->addConfiguration(['domains' => ['example.com', 'http://foo.com']]);

        $provider = $this->getMockBuilder(HttpClientProvider::class)
            ->setMethods(['sendQueues'])
            ->setConstructorArgs([$configManager])
            ->getMock();

        $provider->expects($this->once())
            ->method('sendQueues')
            ->with($input->getQueues())
            ->will($this->returnValue($responses));

        $sender = new Sender($provider, $configManager, new BatchRequestValidator($configManager));

        $actual = $sender->send($input);

        var_dump($actual);

        $this->assertEquals($expect, $actual);
    }

    /**
     *
     */
    public function sendProvider()
    {
        $reqId = 'test_get';

        $batchRequest = new BatchRequest();
        $batchRequest->setQueues([
            [
                (new Request())->setId($reqId)->setUri('http://example.com'),
            ],
        ]);

        $batchResponse = new BatchResponse();
        $batchResponse->setStatus('ok');
        $batchResponse->setData([]);

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