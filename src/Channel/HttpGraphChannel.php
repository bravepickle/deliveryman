<?php

namespace Deliveryman\Channel;

use Deliveryman\Channel\HttpGraph\GraphNode;
use Deliveryman\Channel\HttpGraph\GraphNodeCollection;
use Deliveryman\Channel\HttpGraph\GraphTreeBuilder;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpGraph\ChannelConfig;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\HttpGraph\HttpHeader;
use Deliveryman\Entity\HttpResponse;
use Deliveryman\Entity\ResponseItemInterface;
use Deliveryman\EventListener\BuildResponseEvent;
use Deliveryman\Exception\HttpGraphChannelException;
use Deliveryman\Exception\InvalidArgumentException;
use Deliveryman\Exception\LogicException;
use Deliveryman\Exception\SendingException;
use Deliveryman\Service\ConfigManager;
use Deliveryman\Strategy\MergeRequestConfigStrategy;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class HttpGraphChannel
 * Send messages over HTTP protocol that have dependencies between each other.
 * Will build graph tree according to dependencies and will try to solve dependencies properly
 * @package Deliveryman\Channel
 */
class HttpGraphChannel extends AbstractChannel
{
    const NAME = 'http_graph';

    const MSG_REQUEST_FAILED = 'Request failed to complete.';
    const MSG_UNDEFINED_REQUESTS = 'Requests must be defined.';

    const OPT_RECEIVER_HEADERS = 'receiver_headers';
    const OPT_SENDER_HEADERS = 'sender_headers';
    const OPT_CHANNELS = 'channels';
    const OPT_REQUEST_OPTIONS = 'request_options';
    const OPT_EXPECTED_STATUS_CODES = 'expected_status_codes';
    const OPT_CONFIG_MERGE = 'config_merge';
    const OPT_RESOURCE_FORMAT = 'resource_format';

    const NODE_STATE_SEND_STARTED = 1;
    const NODE_STATE_SEND_FINISHED = 2;

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
     * @var EventDispatcher|null
     */
    protected $dispatcher;

    /**
     * @var GraphTreeBuilder
     */
    protected $treeBuilder;

    /**
     * @var GraphNodeCollection
     */
    protected $nodesCollection;

    /**
     * @var MergeRequestConfigStrategy
     */
    protected $mergeStrategy;

    /**
     * BatchRequestHandler constructor.
     * @param ConfigManager $configManager
     * @param RequestStack|null $requestStack
     * @param EventDispatcherInterface|null $dispatcher
     * @throws \Psr\Cache\InvalidArgumentException
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
        $this->treeBuilder = new GraphTreeBuilder();

        $this->initMergeStrategy();
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
     * @throws \Deliveryman\Exception\LogicException
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws HttpGraphChannelException
     */
    public function send(Envelope $envelope): Envelope
    {
        if (!$envelope->getMessage() instanceof BatchRequest) {
            throw new InvalidArgumentException('Cannot handle message of class: ' .
                get_class($envelope->getMessage()));
        }

        $this->batchRequest = $envelope->getMessage();

        // TODO: validate that input data is array
        if (!$this->batchRequest->getData()) {
            throw new HttpGraphChannelException(self::MSG_UNDEFINED_REQUESTS);
        }

        $this->mergeRequestConfigs();

        if ($this->hasSingleRequest()) {
            $requests = $this->batchRequest->getData();
            $request = reset($requests);
            $this->sendRequest($request);

            return $envelope;
        }

        $this->nodesCollection = new GraphNodeCollection(
            $this->treeBuilder->buildNodesFromRequests($this->batchRequest->getData())
        );

        if ($this->hasSingleArrow()) {
            $this->sendSingleArrow();

            return $envelope;
        }

        $this->sendMultiArrows();

        return $envelope;
    }

    /**
     * Merge request configs together according to settings and update them
     * @throws \Deliveryman\Exception\InvalidArgumentException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function mergeRequestConfigs()
    {
        $generalCfg = $this->batchRequest->getConfig();
        /** @var HttpRequest $request */
        foreach ($this->batchRequest->getData() as $request) {
            $this->mergeStrategy->setConfigMerge(
                $this->getConfigMergeWithFallback($request->getConfig(), $generalCfg)
            );

            $configs = [];
            if ($generalCfg) {
                $configs[] = $generalCfg->toArray();
            }

            if ($request->getConfig()) {
                $configs[] = $request->getConfig()->toArray();
            }

            $mergedConfig = new RequestConfig();
            $mergedConfig->load(
                $this->mergeStrategy->merge(...$configs),
                ['channel_class' => ChannelConfig::class]
            );

            $request->setConfig($mergedConfig);
        }
    }

    /**
     * Send multiple queues concurrently
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendMultiArrows()
    {
        $client = $this->createClient();
        $promises = [];

        foreach ($this->nodesCollection->arrowTailsIterator() as $key => $node) {
            $promise = $this->chainSendRequest($node, $client);
            if ($promise) {
                $promises[$key] = $promise;
            }
        }

        Promise\settle($promises)->wait();
    }

    /**
     * Chain queue of requests
     * @param GraphNode $node
     * @param Client $client
     * @return Promise\PromiseInterface|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function chainSendRequest(GraphNode $node, Client $client)
    {
        /** @var HttpRequest $request */
        $request = $node->getData()['request'];
        $options = $this->buildRequestOptions($request);
        $this->setNodeState($node, self::NODE_STATE_SEND_STARTED);
        // TODO: add event dispatching

        return $client->requestAsync($request->getMethod(), $request->getUri(), $options)
            ->then($this->getChainFulfilledCallback($node, $client, $request),
                $this->getChainRejectedCallback($node, $client, $request));
    }

    /**
     * @param GraphNode $node
     * @param int $state
     * @return $this
     */
    protected function setNodeState(GraphNode $node, int $state): self
    {
        $payload = $node->getData();
        $payload['state'] = $state;
        $node->setData($payload);

        return $this;
    }

    /**
     * Send request synchronously
     * @param HttpRequest $request
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendRequest(HttpRequest $request)
    {
        $options = $this->buildRequestOptions($request);

        $this->createClient()->requestAsync($request->getMethod(), $request->getUri(), $options)
            ->then($this->getSendFulfilledCallback($request), $this->getSendRejectedCallback($request))
            ->wait();
    }

    /**
     * @return bool
     */
    protected function hasSingleRequest(): bool
    {
        return count($this->batchRequest->getData()) === 1;
    }

    /**
     * @return bool
     */
    protected function hasSingleArrow(): bool
    {
        $iterator = $this->nodesCollection->arrowTailsIterator();
        $iterator->rewind();
        $iterator->next();
        return !$iterator->valid(); // check if next value exists in iterating loop
    }

    /**
     * @param HttpRequest $request
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function buildRequestOptions(HttpRequest $request): array
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

        $this->appendInitialRequestHeaders($options[RequestOptions::HEADERS]);

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
     * @param GraphNode $node
     * @param Client $client
     * @param HttpRequest $request
     * @return \Closure
     */
    protected function getChainFulfilledCallback(GraphNode $node, Client $client, HttpRequest $request): \Closure
    {
        return function (ResponseInterface $response) use ($client, $node, $request) {
            $this->setNodeState($node, self::NODE_STATE_SEND_FINISHED);
            /** @var ChannelConfig $channel */
            $channel = $request->getConfig()->getChannel();
            if (!in_array($response->getStatusCode(), (array)$channel->getExpectedStatusCodes())) {
                $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $response));

                $onFail = $request->getConfig()->getOnFail();
                switch ($onFail) {
                    case RequestConfig::CFG_ON_FAIL_PROCEED:
                        // do nothing
                        break;

                    case RequestConfig::CFG_ON_FAIL_ABORT:
                        throw (new HttpGraphChannelException(HttpGraphChannelException::MSG_QUEUE_TERMINATED))
                            ->setRequest($request);

                    case RequestConfig::CFG_ON_FAIL_ABORT_QUEUE:
                        return; // stop chaining requests from queue

                    default:
                        throw new SendingException('Unexpected fail handler type: ' . $onFail);
                }
            } elseif (!$request->getConfig()->getSilent()) {
                $this->addOkResponse($request->getId(), $this->buildResponseData($request, $response));
            }

            $this->chainSendNodesSuccessors($node, $client);
        };
    }

    /**
     * @param GraphNode $node
     * @param Client $client
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function chainSendNodesSuccessors(GraphNode $node, Client $client)
    {
        $promises = [];
        foreach ($node->getSuccessors() as $successor) {
            if ($this->nodeReadyToBeSent($successor)) {
                $promise = $this->chainSendRequest($successor, $client);
                if ($promise) {
                    $promises[] = $promise;
                }
            }
        }

        if (!$promises) {
            return; // reached arrow head
        }

        if (!isset($promises[1])) { // have single request within successor nodes
            $promises[0]->wait();

            return;
        }

        Promise\settle($promises)->wait(); // multiple promises handle in parallel
    }

    protected function nodeReadyToBeSent(GraphNode $node): bool
    {
        foreach ($node->getPredecessors() as $predecessor) {
            if (empty($predecessor->getData()['state']) ||
                $predecessor->getData()['state'] === self::NODE_STATE_SEND_STARTED) {
                return false; // some predecessors need to be processed still
            }
        }

        return true;
    }

    /**
     * @param GraphNode $node
     * @param Client $client
     * @param HttpRequest $request
     * @return \Closure
     */
    protected function getChainRejectedCallback(GraphNode $node, Client $client, HttpRequest $request): \Closure
    {
        return function ($e) use ($client, $node, $request) {
            $this->setNodeState($node, self::NODE_STATE_SEND_FINISHED);
            // TODO: dispatch event on fail
            // TODO: validate all config values and fields before trying to send any data
            // TODO: somehow unify errors output for cases when multiple parallel requests done against single one. Now exceptions are caught
            $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
            if ($e instanceof RequestException && $e->getResponse()) {
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $e->getResponse()));
            }

            $onFail = $request->getConfig()->getOnFail();
            switch ($onFail) {
                case RequestConfig::CFG_ON_FAIL_PROCEED:
                    $this->chainSendNodesSuccessors($node, $client);
                    break;

                case RequestConfig::CFG_ON_FAIL_ABORT:
                    throw (new HttpGraphChannelException(HttpGraphChannelException::MSG_QUEUE_TERMINATED, null, $e))
                        ->setRequest($request);

                case RequestConfig::CFG_ON_FAIL_ABORT_QUEUE:
                    return; // stop chaining requests from queue

                default:
                    throw new SendingException('Unexpected fail handler type: ' . $onFail);
            }
        };
    }

    /**
     * @param HttpRequest $request
     * @return \Closure
     */
    protected function getSendFulfilledCallback(HttpRequest $request): \Closure
    {
        return function (ResponseInterface $response) use ($request) {
            /** @var ChannelConfig $channel */
            $channel = $request->getConfig()->getChannel();
            if (!in_array($response->getStatusCode(), (array)$channel->getExpectedStatusCodes())) {
                $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $response));

                if (in_array($request->getConfig()->getOnFail(), [
                    RequestConfig::CFG_ON_FAIL_ABORT,
                ])) {
                    throw (new HttpGraphChannelException(HttpGraphChannelException::MSG_QUEUE_TERMINATED))
                        ->setRequest($request);
                }

                return;
            }

            if (!$request->getConfig()->getSilent()) {
                $this->addOkResponse($request->getId(), $this->buildResponseData($request, $response));
            }
        };
    }

    /**
     * @param HttpRequest $request
     * @return \Closure
     */
    protected function getSendRejectedCallback(HttpRequest $request): \Closure
    {
        return function ($e) use ($request) {
            $this->addError($request->getId(), self::MSG_REQUEST_FAILED);
            if ($e instanceof RequestException && $e->getResponse()) {
                $this->addFailedResponse($request->getId(), $this->buildResponseData($request, $e->getResponse()));
            }

            switch ($request->getConfig()->getOnFail()) {
                case RequestConfig::CFG_ON_FAIL_ABORT:
                case RequestConfig::CFG_ON_FAIL_ABORT_QUEUE:
                    throw (new HttpGraphChannelException(HttpGraphChannelException::MSG_QUEUE_TERMINATED, null, $e))
                        ->setRequest($request);
                    break;

                case RequestConfig::CFG_ON_FAIL_PROCEED:
                    // do nothing
                    break;

                default:
                    throw new SendingException('Unexpected fail handler type: ' .
                        $request->getConfig()->getOnFail());
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
     * @param HttpResponse|ResponseItemInterface $response
     * @return HttpResponse
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
     * @param array|null $headers
     * @return array|void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function appendInitialRequestHeaders(?array &$headers)
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
            foreach ($initialRequest->headers as $name => $values) {
                foreach ($values as $value) {
                    $headers[$name][] = $value;
                }
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
     * @param HttpRequest $request
     * @param Response|ResponseInterface $srcResponse
     * @return HttpResponse
     * @throws LogicException
     */
    protected function buildResponseData(HttpRequest $request, $srcResponse): HttpResponse
    {
        // TODO: dispatcher extend with config request resulting object
        // TODO: add headers from config
        $targetResponse = new HttpResponse();
        $targetResponse->setId($request->getId());
        $targetResponse->setStatusCode($srcResponse->getStatusCode());
        $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));

        $format = $request->getConfig()->getFormat();
        $this->genResponseBody($format, $srcResponse, $targetResponse);

        if ($this->dispatcher) {
            $event = new BuildResponseEvent($targetResponse, $srcResponse, $request->getConfig());
            $this->dispatcher->dispatch(BuildResponseEvent::EVENT_POST_BUILD, $event);
            $targetResponse = $event->getTargetResponse();
        }

        return $targetResponse;
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
                $headers[] = new HttpHeader($name, $value);
            }
        }

        return $headers;
    }

    /**
     * @param string $format
     * @param ResponseInterface $srcResponse
     * @param HttpResponse $targetResponse
     * @throws LogicException
     */
    protected function genResponseBody($format, ResponseInterface $srcResponse, HttpResponse $targetResponse): void
    {
        // TODO: add validation of data format before processing requests and sending them
        switch ($format) {
            case HttpResponse::FORMAT_JSON:
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
            case HttpResponse::FORMAT_TEXT:
                $targetResponse->setData($srcResponse->getBody()->getContents());
                break;
            case HttpResponse::FORMAT_BINARY:
                // TODO: implement me! Download files to tmp dir and return links to those files
                // TODO: implement FileStorageInterface to abstract place for storing files
            default:
                throw new LogicException('Not supported data format: ' . $format);
        }
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function sendSingleArrow(): void
    {
        $client = $this->createClient();
        /** @var GraphNode $node */
        foreach ($this->nodesCollection->arrowTailsIterator() as $node) {
            $promise = $this->chainSendRequest($node, $client);
            if ($promise) {
                $promise->wait();
            }
            break;
        }
    }

    /**
     * @param RequestConfig|null $requestCfg
     * @param RequestConfig|null $globalConfig
     * @return mixed|string|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getConfigMergeWithFallback(?RequestConfig $requestCfg, ?RequestConfig $globalConfig)
    {
        if ($requestCfg && $requestCfg->getConfigMerge()) {
            return $requestCfg->getConfigMerge();
        } elseif ($globalConfig && $globalConfig->getConfigMerge()) {
            return $globalConfig->getConfigMerge();
        }

        return $this->getMasterConfig()[self::OPT_CONFIG_MERGE];
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function initMergeStrategy(): void
    {
        $masterConfig = $this->getMasterConfig();
        $defaults = [
            'silent' => $masterConfig['silent'],
            'format' => $masterConfig['resource_format'],
            'configMerge' => $masterConfig['config_merge'],
            'onFail' => $masterConfig['on_fail'],
        ];

        $defaults['channel'] = [
            'expectedStatusCodes' => $masterConfig['channels'][$this->getName()]['expected_status_codes']
        ];

        $this->mergeStrategy = new MergeRequestConfigStrategy($defaults);
    }

}