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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class HttpChannel
 * Send messages over HTTP protocol
 * @package Deliveryman\Channel
 */
class HttpChannel extends AbstractChannel
{
    const MSG_REQUEST_FAILED = 'Request failed to complete.';
    const OPT_RECEIVER_HEADERS = 'receiver_headers';
    const OPT_SENDER_HEADERS = 'sender_headers';
    const OPT_CHANNELS = 'channels';
    const OPT_REQUEST_OPTIONS = 'request_options';

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var RequestStack|null
     */
    protected $requestStack;

    /**
     * Sender constructor.
     * @param ConfigManager $configManager
     * @param RequestStack|null $requestStack
     */
    public function __construct(ConfigManager $configManager, ?RequestStack $requestStack = null)
    {
        $this->configManager = $configManager;
        $this->requestStack = $requestStack;
    }

    /**
     * @return Client
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function createClient()
    {
        $appConfig = $this->configManager->getConfiguration();
        $options = $appConfig[self::OPT_CHANNELS][$this->getName()][self::OPT_REQUEST_OPTIONS] ?? [];

        // never allow throwing exceptions. Statuses should be handled elsewhere
        $options[RequestOptions::HTTP_ERRORS] = false;

        return new Client($options);
    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getChannelConfig()
    {
        return $this->getMasterConfig()[self::OPT_CHANNELS][$this->getName()];
    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getMasterConfig()
    {
        if ($this->config === null) {
            $this->config = $this->configManager->getConfiguration();
        }

        return $this->config;
    }

    /**
     * @inheritdoc
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function send(array $queues)
    {
        if ($this->hasSingleRequest($queues)) {
            $request = $this->getFirstRequest($queues);
            $this->sendRequest($request);
        } elseif ($this->hasSingleQueue($queues)) {
            $this->sendQueue($this->getFirstQueue($queues));
        } else {
            $this->sendMultiQueues($queues);
        }
    }

    /**
     * Send multiple queues concurrently
     * @param array $queues
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendMultiQueues(array $queues)
    {
        $client = $this->createClient();
        $promises = [];

        foreach ($queues as $key => $queue) {
            $promise = $this->chainSendRequest($queue, $client);
            if ($promise) {
                $promises[$key] = $promise;
            }
        }

        Promise\settle($promises)->wait();
    }

    /**
     * Chain queue of requests
     * @param array|null $queue
     * @param Client $client
     * @return Promise\PromiseInterface|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function chainSendRequest(?array $queue, Client $client)
    {
        if (!$queue) {
            return null;
        }

        /** @var Request $request */
        $request = array_shift($queue);
        if ($request) {
            $options = $this->buildRequestOptions($request);

            return $client->requestAsync($request->getMethod(), $request->getUri(), $options)
                ->then($this->getChainFulfilledCallback($queue, $client, $request),
                    $this->getChainRejectedCallback($queue, $client, $request));
        }

        return null;
    }

    /**
     * Send requests queue
     * @param array|Request[] $queue
     * @return array|Response[]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendQueue(array $queue)
    {
        $responses = [];
        foreach ($queue as $request) {
            $response = $this->sendRequest($request);
            if ($response) {
                $responses[$request->getId()] = $response;
            }
        }

        return $responses;
    }

    /**
     * Send request synchronously
     * @param Request $request
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendRequest(Request $request)
    {
        $options = $this->buildRequestOptions($request);

        $this->createClient()->requestAsync($request->getMethod(), $request->getUri(), $options)
            ->then($this->getSendFulfilledCallback($request), $this->getSendRejectedCallback($request))
            ->wait();
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
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function buildRequestOptions(Request $request): array
    {
        $options = [];
        if ($request->getQuery()) {
            $options[RequestOptions::QUERY] = $request->getQuery();
        }

        if ($request->getHeaders()) {
            foreach ($request->getHeaders() as $header) {
                $options[RequestOptions::HEADERS][$header->getName()][] = $header->getValue();
            }
        } else {
            $options[RequestOptions::HEADERS] = [];
        }

        $this->appendInitialRequestHeaders($request, $options[RequestOptions::HEADERS]);

        // TODO: check resource input format from configs
        if ($request->getData()) {
            if (is_array($request->getData()) || is_bool($request->getData())) {
                $options[RequestOptions::JSON] = $request->getData(); // set JSON format
            } else {
                $options[RequestOptions::BODY] = $request->getData(); // set raw
            }
        }

        return $options;
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getExpectedStatusCodesWithFallback(Request $request)
    {
        if ($request && $request->getConfig() && $request->getConfig()->getExpectedStatusCodes()) {
            return (array)$request->getConfig()->getExpectedStatusCodes();
        }

        return $this->getMasterConfig()['expected_status_codes'] ?? []; // fallback
    }

    /**
     * @param Request $request
     * @return string|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getOnFailWithFallback(Request $request)
    {
        if ($request && $request->getConfig() && $request->getConfig()->getOnFail()) {
            return $request->getConfig()->getOnFail();
        }

        return $this->getMasterConfig()['on_fail']; // fallback
    }

    /**
     * @param array|null $queue
     * @param Client $client
     * @param Request $request
     * @return \Closure
     */
    protected function getChainFulfilledCallback(?array $queue, Client $client, Request $request): \Closure
    {
        return function (ResponseInterface $response) use ($client, $queue, $request) {
            if (!in_array($response->getStatusCode(), $this->getExpectedStatusCodesWithFallback($request))) {
                $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
                $this->addFailedResponse($request->getId(), $response);

                switch ($request->getConfig()->getOnFail()) {
                    case RequestConfig::CONFIG_ON_FAIL_PROCEED:
                        // do nothing
                        break;

                    case RequestConfig::CONFIG_ON_FAIL_ABORT:
                        throw (new ChannelException(ChannelException::MSG_QUEUE_TERMINATED))
                            ->setRequest($request);

                    case RequestConfig::CONFIG_ON_FAIL_ABORT_QUEUE:
                        return; // stop chaining requests from queue

                    default:
                        throw new SendingException('Unexpected fail handler type: ' .
                            $request->getConfig()->getOnFail());
                }
            }

            $this->addOkResponse($request->getId(), $response);
            $this->chainSendRequest($queue, $client)->wait();
        };
    }

    /**
     * @param array|null $queue
     * @param Client $client
     * @param Request $request
     * @return \Closure
     */
    protected function getChainRejectedCallback(?array $queue, Client $client, Request $request): \Closure
    {
        return function ($e) use ($client, $queue, $request) {
            // TODO: check unexpected_status_codes if should be marked as error
            // TODO: dispatch event on fail
            $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
            if ($e instanceof RequestException && $e->getResponse()) {
                $this->addFailedResponse($request->getId(), $e->getResponse());
            }

            switch ($request->getConfig()->getOnFail()) {
                case RequestConfig::CONFIG_ON_FAIL_PROCEED:
                    $this->chainSendRequest($queue, $client)->wait();
                    break;

                case RequestConfig::CONFIG_ON_FAIL_ABORT:
                    throw (new ChannelException(ChannelException::MSG_QUEUE_TERMINATED, null, $e))
                        ->setRequest($request);

                case RequestConfig::CONFIG_ON_FAIL_ABORT_QUEUE:
                    return; // stop chaining requests from queue

                default:
                    throw new SendingException('Unexpected fail handler type: ' .
                        $request->getConfig()->getOnFail());
            }
        };
    }

    /**
     * @param Request $request
     * @return \Closure
     */
    protected function getSendFulfilledCallback(Request $request): \Closure
    {
        return function (ResponseInterface $response) use ($request) {
            if (!in_array($response->getStatusCode(), $this->getExpectedStatusCodesWithFallback($request)) &&
                in_array($this->getOnFailWithFallback($request), [
                    RequestConfig::CONFIG_ON_FAIL_ABORT,
                    RequestConfig::CONFIG_ON_FAIL_ABORT_QUEUE,
                ])
            ) {
                // todo: save failed objects and succeeded responses in separate data sets
                $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
                $this->addFailedResponse($request->getId(), $response);

                throw (new ChannelException(ChannelException::MSG_QUEUE_TERMINATED))
                    ->setRequest($request);
            }

            $this->addOkResponse($request->getId(), $response);
        };
    }

    /**
     * @param Request $request
     * @return \Closure
     */
    protected function getSendRejectedCallback(Request $request): \Closure
    {
        return function ($e) use ($request) {
            $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
            if ($e instanceof RequestException && $e->getResponse()) {
                $this->addFailedResponse($request->getId(), $e->getResponse());
            }

            switch ($this->getOnFailWithFallback($request)) {
                case RequestConfig::CONFIG_ON_FAIL_ABORT:
                case RequestConfig::CONFIG_ON_FAIL_ABORT_QUEUE:
                    throw (new ChannelException(ChannelException::MSG_QUEUE_TERMINATED, null, $e))
                        ->setRequest($request);
                    break;

                case RequestConfig::CONFIG_ON_FAIL_PROCEED:
                    // do nothing
                    break;

                default:
                    throw new SendingException('Unexpected fail handler type: ' .
                        $this->getOnFailWithFallback($request));
            }
        };
    }

    /**
     * @inheritdoc
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function addOkResponse($path, ResponseInterface $response)
    {
        return parent::addOkResponse($path, $this->filterResponseHeaders($response));
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function filterResponseHeaders(ResponseInterface $response)
    {
        $allowedHeaders = $this->getChannelConfig()[self::OPT_RECEIVER_HEADERS] ?? [];
        if ($allowedHeaders) { // if empty consider that all allowed
            foreach ($response->getHeaders() as $name => $values) {
                if (!in_array($name, $allowedHeaders)) {
                    $response = $response->withoutHeader($name);
                }
            }
        }

        return $response;
    }

    /**
     * @param Request $request
     * @param array|null $headers
     * @return array|void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function appendInitialRequestHeaders(Request $request, ?array &$headers)
    {
        if (!$this->requestStack) {
            return;
        }

        $initialRequest = $this->requestStack->getCurrentRequest();
        if (!$initialRequest) {
            return;
        }

        $allowedHeaders = $this->getChannelConfig()[self::OPT_SENDER_HEADERS] ?? [];
        if (!$allowedHeaders) { // if empty consider that all needed
            foreach ($request->getHeaders() as $header) {
                $headers[$header->getName()][] = $header->getValue();
            }

            return;
        }

        foreach ($allowedHeaders as $allowedHeader) {
            if ($initialRequest->headers->has($allowedHeader)) {
                $headers[$allowedHeader] = $initialRequest->headers->get($allowedHeader);
            }
        }
    }

}