<?php

namespace DeliverymanTest\Service;


use Deliveryman\ClientProvider\ClientProviderInterface;
use Deliveryman\ClientProvider\HttpClientProvider;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Service\BatchRequestValidator;
use Deliveryman\Service\ConfigManager;
use Deliveryman\Service\Sender;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SenderTest extends TestCase
{
    /**
     * Sending batch requests
     * @dataProvider sendProvider
     * @param BatchRequest $input
     * @param array $responses
     * @param BatchResponse $expected
     * @throws \Deliveryman\Exception\SendingException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Deliveryman\Exception\SerializationException
     */
    public function testSend(BatchRequest $input, array $responses, BatchResponse $expected)
    {
        $configManager = new ConfigManager();
        $configManager->addConfiguration(['domains' => ['example.com', 'http://foo.com']]);

        /** @var ClientProviderInterface|MockObject $provider */
        $provider = $this->getMockBuilder(HttpClientProvider::class)
            ->setMethods(['send'])
            ->setConstructorArgs([$configManager])
            ->getMock();

        $provider->expects($this->once())
            ->method('send')
            ->with($input->getQueues())
            ->will($this->returnValue($responses));

        $sender = new Sender($provider, $configManager, new BatchRequestValidator($configManager));
        $actual = $sender->send($input);

        $this->assertEquals($expected, $actual);
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

        $returnedResponses[$reqId] = (new \Deliveryman\Entity\Response())
            ->setId($reqId)
            ->setHeaders([new RequestHeader('Content-Type', 'application/json')])
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