<?php

namespace Deliveryman\ClientProvider;

use Deliveryman\Entity\Request;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;

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
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function createClient()
    {
        $options = $this->configManager->getConfiguration()['providers'][$this->getName()]['request_options'] ?? [];
        // never allow throwing exceptions. Statuses should be handled elsewhere
        $options[RequestOptions::HTTP_ERRORS] = false;

        return new Client($options);
    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getMasterConfig()
    {
        return $this->configManager->getConfiguration();
    }

    /**
     * @inheritdoc
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function send(array $queues)
    {
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
    }

    /**
     * Send requests queue
     * @param array|Request[] $queue
     * @return array|Response[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
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
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendRequest(Request $request)
    {
        $options = [];
        if ($request->getQuery()) {
            $options['query'] = $request->getQuery();
        }

        // TODO: add headers from library config, general config, request config, merged together according to settings
        // TODO: if allowed, pass headers from initial client to all requests. Proper sequence must be held.
        // Order as follows for headers:
        //      permissions check for allowed headers & config merge strategy ->
        //      general batch request config merged with request config ->
        //      request headers (always add if app config allows them) ->
        //      library config (most priority)
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'http';
    }

}