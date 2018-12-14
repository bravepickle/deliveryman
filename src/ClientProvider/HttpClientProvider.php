<?php

namespace Deliveryman\ClientProvider;

use Deliveryman\Entity\Request;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Class HttpClientProvider
 * Send messages over HTTP protocol
 * @package Deliveryman\ClientProvider
 */
class HttpClientProvider extends AbstractClientProvider
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * Sender constructor.
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * @return Client
     */
    public function createClient()
    {
        return new Client([
            'allow_redirects' => false,
            'auth' => null,
            'connect_timeout' => 10,
            'timeout' => 30,
            'debug' => false,
            'delay' => null,
            'http_errors' => false,
            'synchronous' => null,
        ]);
    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getMasterConfig()
    {
        return $this->configManager->getConfiguration();
    }

//    /**
//     * @inheritdoc
//     */
//    public function send(RequestInterface $request, ?RequestMetaDataInterface $metaData)
//    {
//        // TODO: Implement send() method.
//        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
//    }

    /**
     * @inheritdoc
     */
    public function send(array $queues)
    {
//        $config = $this->getMasterConfig();

        if ($this->hasSingleRequest($queues)) {
            $request = $this->getFirstRequest($queues);

            return [$request->getId() => $this->sendRequest($request)];
        } elseif ($this->hasSingleQueue($queues)) {
            return $this->sendQueue($this->getFirstQueue($queues));
        } else {
            die("Run requests in parallel! Use PSR7 promises & async requests for chaining!!!\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");
        }

        // TODO: handle errors, check expectedStatusCodes, do not throw exceptions
        // TODO: various behavior on when:
        // - single queue - run normally
        // - multiple queues but single request per each - run in parallel
        // - multiple queues with various numbers of requests - run in forked scripts or implement queues consumers-receivers etc.
        // TODO: Implement sendQueue() method.
        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
    }

    /**
     * Send requests queue
     * @param array|Request[] $queue
     * @return array|Response[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendQueue(array $queue)
    {
        $responses = [];
        foreach ($queue as $id => $request) {
            $responses[$id] = $this->sendRequest($request);
        }

        return $responses;
    }

    /**
     * Send request synchronously
     * @param Request $request
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendRequest(Request $request)
    {
        $options = [];
        if ($request->getQuery()) {
            $options['query'] = $request->getQuery();
        }

        // TODO: add headers from library config, general config, request config, merged together according to settings
        // TODO: if allowed, pass headers from initial client to all requests
        if ($request->getHeaders()) {
            $options['headers'] = $request->getHeaders();
        }

        $psrResponse = $this->createClient()->request($request->getMethod(), $request->getUri(), $options);

        return $psrResponse;
    }

    /**
     * @param array $queues
     * @return bool
     */
    protected function hasSingleRequest(array $queues): bool
    {
        if (!$this->hasSingleQueue($queues)) {
            return false;
        }

        $queue = reset($queues);

        return count($queue) === 1;
    }

    /**
     * @param array $queues
     * @return bool
     */
    protected function hasSingleQueue(array $queues): bool
    {
        return count($queues) === 1;
    }

    /**
     * @param array $queues
     * @return Request
     */
    protected function getFirstRequest(array $queues)
    {
        $queue = $this->getFirstQueue($queues);

        return reset($queue);
    }

    /**
     * @param array $queues
     * @return array
     */
    protected function getFirstQueue(array $queues)
    {
        return reset($queues);
    }

}