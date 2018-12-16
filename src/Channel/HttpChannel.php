<?php

namespace Deliveryman\Channel;

use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Exception\ChannelException;
use Deliveryman\Exception\SendingException;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;

/**
 * Class HttpChannel
 * Send messages over HTTP protocol
 * @package Deliveryman\Channel
 */
class HttpChannel extends AbstractChannel
{
    const MSG_REQUEST_FAILED = 'Request failed to complete.';

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
        $options = $this->configManager->getConfiguration()['channels'][$this->getName()]['request_options'] ?? [];
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
            return $this->sendMultiQueues($queues);
        }
    }

    /**
     * Send multiple queues concurrently
     * @param array $queues
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendMultiQueues(array $queues)
    {
        $client = $this->createClient();
        $responses = [];
        $promises = [];

        foreach ($queues as $key => $queue) {
            $promises[$key] = $this->chainSendRequest($queue, $client, $responses);
        }

//        // Wait on all of the requests to complete. Throws a ConnectException if any of the requests fail
//        // TODO: catch ConnectException if can be thrown with http_errors = false
//        $results = Promise\unwrap($promises);

        // Wait for the requests to complete, even if some of them fail
//        $results = Promise\settle($promises)->wait();
        Promise\settle($promises)->wait();

        return $responses;
    }

    /**
     * Chain queue of requests
     * @param array|null $queue
     * @param Client $client
     * @param array $responses
     * @return Promise\PromiseInterface|null
     */
    protected function chainSendRequest(?array $queue, Client $client, array &$responses)
    {
        if (!$queue) {
            return null;
        }

        /** @var Request $request */
        $request = array_shift($queue);
        if ($request) {
            $options = $this->buildRequestOptions($request);
            return $client->requestAsync($request->getMethod(), $request->getUri(), $options)
                ->then(function (ResponseInterface $response) use ($responses, $client, $queue, $request) {
                    $responses[$request->getId()] = $response;
                    $this->chainSendRequest($queue, $client, $responses);
                }, function (RequestException $e) use ($responses, $client, $queue, $request) {
                    // TODO: check unexpectedStatusCodes if should be marked as error
                    // TODO: dispatch event on fail
                    $this->addError($request->getId(), self::MSG_REQUEST_FAILED);

                    switch ($request->getConfig()->getOnFail()) {
                        case RequestConfig::CONFIG_ON_FAIL_PROCEED:
                            $this->chainSendRequest($queue, $client, $responses);
                            break;

                        case RequestConfig::CONFIG_ON_FAIL_ABORT:
                            throw (new ChannelException(ChannelException::MSG_QUEUE_TERMINATED, null, $e))
                                ->setRequest($request)
                                ->setResponses($responses)
                            ;
                            break;

                        case RequestConfig::CONFIG_ON_FAIL_ABORT_QUEUE:
                            return; // stop chaining requests from queue

                        default:
                            throw new SendingException('Unexpected fail handler type: ' .
                                $request->getConfig()->getOnFail());
                    }
                });
        }

        return null;
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
        $options = $this->buildRequestOptions($request);

        return $this->createClient()->request($request->getMethod(), $request->getUri(), $options);
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

    /**
     * @param Request $request
     * @return array
     */
    protected function buildRequestOptions(Request $request): array
    {
        $options = [];
        if ($request->getQuery()) {
            $options['query'] = $request->getQuery();
        }

        if ($request->getHeaders()) {
            $options['headers'] = [];
            foreach ($request->getHeaders() as $header) {
                $options['headers'][$header->getName()][] = $header->getValue();
            }
        }

        return $options;
    }

}