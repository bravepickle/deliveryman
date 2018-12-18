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
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Yaml\Yaml;

/**
 * Class IntegrationTest
 * @package DeliverymanTest\Integration
 */
class IntegrationTest extends TestCase
{
    /**
     * Data sets for data provider taken from parsed files
     * @var array
     */
    protected static $fixtures;

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

        self::$fixtures = Yaml::parseFile(__DIR__ . '/../Resources/fixtures/integration.fixtures.yaml');

        return self::$fixtures[$name] ?? [];
    }

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
     * @dataProvider batchRequestProvider
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
    )
    {
        $mockHandler = new MockHandler($responses);
        $config['channels']['http']['request_options']['handler'] = HandlerStack::create(function (
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
        return $this->prepareProviderData(self::getFixtures(__FUNCTION__));
    }

    /**
     * Configs usage testing in requests
     * @return array
     */
    public function configProvider()
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

}