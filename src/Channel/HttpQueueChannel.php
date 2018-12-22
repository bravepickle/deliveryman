<?php

namespace Deliveryman\Channel;

use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpQueue\ChannelConfig;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Entity\HttpQueue\ResponseData;
use Deliveryman\Entity\ResponseItemInterface;
use Deliveryman\EventListener\BuildResponseEvent;
use Deliveryman\Exception\ChannelException;
use Deliveryman\Exception\SendingException;
use Deliveryman\Service\ConfigManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class HttpChannel
 * Send messages over HTTP protocol
 * @package Deliveryman\Channel
 */
class HttpQueueChannel extends AbstractChannel
{
    const MSG_REQUEST_FAILED = 'Request failed to complete.';
    const OPT_RECEIVER_HEADERS = 'receiver_headers';
    const OPT_SENDER_HEADERS = 'sender_headers';
    const OPT_CHANNELS = 'channels';
    const OPT_REQUEST_OPTIONS = 'request_options';
    const OPT_EXPECTED_STATUS_CODES = 'expected_status_codes';
    const OPT_CONFIG_MERGE = 'config_merge';
    const OPT_RESOURCE_FORMAT = 'resource_format';

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
     * @var BatchRequest|null
     */
    protected $batchRequest;

    /**
     * @var EventDispatcherInterface|null
     */
    protected $dispatcher;

    /**
     * Sender constructor.
     * @param ConfigManager $configManager
     * @param RequestStack|null $requestStack
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(
        ConfigManager $configManager,
        ?RequestStack $requestStack = null,
        ?EventDispatcherInterface $dispatcher = null
    )
    {
        $this->configManager = $configManager;
        $this->requestStack = $requestStack;
        $this->dispatcher = $dispatcher;
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
    public function send(BatchRequest $batchRequest)
    {
        $this->batchRequest = $batchRequest;
        $queues = $batchRequest->getQueues();
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
        return 'http_queue';
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
     * @throws ChannelException
     */
    protected function getExpectedStatusCodesWithFallback(Request $request)
    {
        $globalConfig = $this->batchRequest->getConfig() ? $this->batchRequest->getConfig()->getChannel() : null;
        $requestConfig = $request->getConfig() ? $request->getConfig()->getChannel() : null;
        $statusCodes = $this->mergeExpectedStatusCodes($requestConfig, $globalConfig);

        return $statusCodes ?: $this->getChannelConfig()[self::OPT_EXPECTED_STATUS_CODES] ?? []; // fallback
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
     * @param ChannelConfig|null $requestCfg
     * @param RequestConfig|null $globalConfig
     * @return array|null
     * @throws ChannelException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function mergeExpectedStatusCodes(?ChannelConfig $requestCfg, ?RequestConfig $globalConfig): ?array
    {
        $configMerge = $globalConfig ? $globalConfig->getConfigMerge() : $this->getMasterConfig()[self::OPT_CONFIG_MERGE];
        $generalCfg = $globalConfig ? $globalConfig->getChannel() : null;
        $channelConfig = $this->getChannelConfig();
        switch ($configMerge) {
            case RequestConfig::CONFIG_MERGE_IGNORE:
                return $channelConfig[self::OPT_EXPECTED_STATUS_CODES];
            case RequestConfig::CONFIG_MERGE_FIRST:
                if ($requestCfg && $requestCfg->getExpectedStatusCodes()) {
                    return $requestCfg->getExpectedStatusCodes();
                } elseif ($generalCfg && $generalCfg->getExpectedStatusCodes()) {
                    return $generalCfg->getExpectedStatusCodes();
                } else {
                    return $channelConfig[self::OPT_EXPECTED_STATUS_CODES];
                }
                break;
            case RequestConfig::CONFIG_MERGE_UNIQUE:
                if ($requestCfg && $requestCfg->getExpectedStatusCodes()) {
                    if ($generalCfg && $generalCfg->getExpectedStatusCodes()) {
                        return array_merge(
                            $requestCfg->getExpectedStatusCodes(),
                            $generalCfg->getExpectedStatusCodes()
                        );
                    } else {
                        return $requestCfg->getExpectedStatusCodes();
                    }
                } elseif ($generalCfg && $generalCfg->getExpectedStatusCodes()) {
                    if ($requestCfg && $requestCfg->getExpectedStatusCodes()) {
                        return array_merge(
                            $requestCfg->getExpectedStatusCodes(),
                            $generalCfg->getExpectedStatusCodes()
                        );
                    } else {
                        return $generalCfg->getExpectedStatusCodes();
                    }
                } else {
                    return $channelConfig[self::OPT_EXPECTED_STATUS_CODES];
                }

            default:
                // TODO: logic exception
                throw new ChannelException('Unexpected config merge strategy type: ' . $configMerge);
        }
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
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $response));

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

            $this->addOkResponse($request->getId(), $this->buildResponseData($request, $response));
            $promise = $this->chainSendRequest($queue, $client);
            if ($promise) {
                $promise->wait();
            }
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
            // TODO: dispatch event on fail
            $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
            if ($e instanceof RequestException && $e->getResponse()) {
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $e->getResponse()));
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
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $response));

                throw (new ChannelException(ChannelException::MSG_QUEUE_TERMINATED))
                    ->setRequest($request);
            }

            $this->addOkResponse($request->getId(), $this->buildResponseData($request, $response));
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
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $e->getResponse()));
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
    public function addOkResponse($path, ResponseItemInterface $response)
    {
        return parent::addOkResponse($path, $this->filterResponseHeaders($response));
    }

    /**
     * @inheritdoc
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function addFailedResponse($path, ResponseItemInterface $response)
    {
        return parent::addFailedResponse($path, $this->filterResponseHeaders($response));
    }

    /**
     * @param ResponseData|ResponseItemInterface $response
     * @return ResponseData
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function filterResponseHeaders($response)
    {
        $allowedHeaders = $this->getChannelConfig()[self::OPT_RECEIVER_HEADERS] ?? [];
        if ($allowedHeaders) { // if empty consider that all allowed
            $headers = [];
            foreach ($response->getHeaders() as $header) {
                if (in_array($header->getName(), $allowedHeaders)) {
                    $headers[] = $header;
                }
            }

            $response->setHeaders($headers);
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

    /**
     * @param Request $request
     * @param Response|ResponseInterface $srcResponse
     * @return ResponseData
     * @throws ChannelException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function buildResponseData(Request $request, $srcResponse): ResponseData
    {
        // TODO: dispatcher extend with config request resulting object
        // TODO: add headers from config

        $requestConfig = $request->getConfig();

        $targetResponse = new ResponseData();
        $targetResponse->setId($request->getId());
        $targetResponse->setStatusCode($srcResponse->getStatusCode());
        $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));

        // TODO: check config merge strategy
        if ($requestConfig && $requestConfig->getFormat()) {
            $format = $requestConfig->getFormat();
        } else {
            $format = $this->getMasterConfig()[self::OPT_RESOURCE_FORMAT];
        }

        $this->genResponseBody($format, $srcResponse, $targetResponse);

        if ($this->dispatcher) {
            $event = new BuildResponseEvent($targetResponse, $srcResponse, $requestConfig);
            $this->dispatcher->dispatch(BuildResponseEvent::EVENT_POST_BUILD, $event);
            $targetResponse = $event->getTargetResponse();
        }

        return $targetResponse;

//        if (!$requestConfig->getSilent()) {
//            $succeededResp[$targetResponse->getId()] = $targetResponse;
//        }

//        foreach ($channel->getFailedResponses() as $id => $srcResponse) {
//            $requestConfig = $requests[$id]->getConfig();
//
//            $targetResponse = new Response();
//            $targetResponse->setId($id);
//            $targetResponse->setStatusCode($srcResponse->getStatusCode());
//            $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));
//
//            $this->genResponseBody($requestConfig->getFormat(), $srcResponse, $targetResponse);
//
//            if ($this->dispatcher) {
//                $event = new BuildResponseEvent($targetResponse, $srcResponse, $requestConfig);
//                $this->dispatcher->dispatch(BuildResponseEvent::EVENT_FAILED_POST_BUILD, $event);
//                $targetResponse = $event->getTargetResponse();
//            }
//
//            $failedResp[$targetResponse->getId()] = $targetResponse;
//        }
//
//        return [$succeededResp, $failedResp];
    }

    /**
     * @param ResponseInterface $srcResponse
     * @return array
     */
    protected function buildResponseHeaders(ResponseInterface $srcResponse): array
    {
        $headers = [];
        foreach ($srcResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = new RequestHeader($name, $value);
            }
        }

        return $headers;
    }

    /**
     * @param string $format
     * @param ResponseInterface $srcResponse
     * @param ResponseData $targetResponse
     * @throws ChannelException
     */
    protected function genResponseBody($format, ResponseInterface $srcResponse, ResponseData $targetResponse): void
    {
        switch ($format) {
            case ResponseData::FORMAT_JSON:
                // TODO: if exception thrown then somehow mark response as failed and write some error info
                $data = $srcResponse->getBody()->getContents();
                if ($data === '' || $data === null) {
                    $targetResponse->setData(null);
                } else {
                    try {
                        $targetResponse->setData((new JsonDecode())
                            ->decode($data, 'json', ['json_decode_associative' => true]));
                    } catch (NotEncodableValueException $e) {
                        // TODO: add event dispatcher, if defined
                        $targetResponse->setData($data); // set raw data
                    }
                }
                break;
            case ResponseData::FORMAT_TEXT:
                $targetResponse->setData($srcResponse->getBody()->getContents());
                break;
            case ResponseData::FORMAT_BINARY:
                // TODO: implement me! Download files to tmp dir and return links to those files
                // TODO: implement FileStorageInterface to abstract place for storing files
            default:
                throw new ChannelException('Not supported format: ' . $format);
        }
    }

}