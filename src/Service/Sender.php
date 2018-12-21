<?php

namespace Deliveryman\Service;


use Deliveryman\Channel\ChannelInterface;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Entity\Response;
use Deliveryman\EventListener\BuildResponseEvent;
use Deliveryman\EventListener\EventSender;
use Deliveryman\Exception\ChannelException;
use Deliveryman\Exception\SendingException;
use Deliveryman\Exception\SerializationException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class Sender
 * Is a facade for sending parsed batch request
 * @package Deliveryman\Service
 */
class Sender
{
    /**
     * @var ChannelInterface
     */
    protected $channel;

    /**
     * @var EventDispatcherInterface|null
     */
    protected $dispatcher;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var BatchRequestValidator
     */
    protected $validator;

    /**
     * Sender constructor.
     * @param ChannelInterface $channel
     * @param ConfigManager $configManager
     * @param BatchRequestValidator $batchRequestValidator
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(
        ChannelInterface $channel,
        ConfigManager $configManager,
        BatchRequestValidator $batchRequestValidator,
        ?EventDispatcherInterface $dispatcher = null
    )
    {
        $this->channel = $channel;
        $this->configManager = $configManager;
        $this->dispatcher = $dispatcher;
        $this->validator = $batchRequestValidator;
    }

    /**
     * Process batch request queries
     * @param BatchRequest $batchRequest
     * @return BatchResponse
     * @throws SendingException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws SerializationException
     */
    public function send(BatchRequest $batchRequest)
    {
        // 0. check global config settings
        // 1. loop through queues to send requests in parallel
        // 2. merge configs according to settings per request
        // 3. create queues and process them accordingly
        // 4. dispatch events per requests, queues etc.
        // 5. validate batch request allowed by master config

        $this->channel->clear();

        if (!$batchRequest->getQueues()) {
            throw new SendingException('No queues with requests specified to process.');
        }

        $channel = $this->channel;

        $errors = $this->validator->validate($batchRequest);
        if (!empty($errors)) {
            return $this->wrapErrors($errors);
        }

        $requests = $this->mergeConfigsPerRequest($batchRequest);

        $aborted = false;
        if (!$this->dispatcher) {
            $this->dispatchSend($batchRequest, $channel, $aborted);
        } else {
            $event = (new EventSender())
                ->setBatchRequest($batchRequest)
                ->setChannel($channel);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_PRE_SEND, $event);
            $batchRequest = $event->getBatchRequest();     // batch request can be changed
            $channel = $event->getChannel(); // client provider may be redefined on-fly

            $this->dispatchSend($batchRequest, $channel, $aborted);

            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_POST_SEND, $event);
            $channel = $event->getChannel();
        }

        return $this->wrapResponses($channel, $requests, $aborted);
    }

    /**
     * @param ChannelInterface $channel
     * @param array|Request[] $requests
     * @param bool $aborted
     * @return BatchResponse
     * @throws SerializationException
     */
    protected function wrapResponses(
        ChannelInterface $channel,
        array $requests,
        bool $aborted
    ): BatchResponse
    {
        list($okResp, $failResp) = $this->buildResponses($channel, $requests);

        $batchResponse = new BatchResponse();
        if ($aborted) {
            $batchResponse->setStatus(BatchResponse::STATUS_ABORTED);
            $batchResponse->setErrors($channel->getErrors());
        } else {
            if ($channel->hasErrors()) {
                $batchResponse->setStatus(BatchResponse::STATUS_FAILED);
                // TODO: format before sending to client???
                $batchResponse->setErrors($channel->getErrors());
            } else {
                $batchResponse->setStatus(BatchResponse::STATUS_SUCCESS);
            }
        }

        if ($okResp) {
            $batchResponse->setData($okResp);
        }

        if ($failResp) {
            if (!$aborted) {
                $batchResponse->setStatus(BatchResponse::STATUS_FAILED);
            }
            $batchResponse->setFailed($failResp);
        }

        return $batchResponse;
    }

    protected function wrapErrors(array $errors): BatchResponse
    {
        $batchResponse = new BatchResponse();
        $batchResponse->setStatus(BatchResponse::STATUS_ABORTED)
            ->setErrors($errors);

        return $batchResponse;
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
     * Return list of request IDs map with resource expected formats
     * @param array $queues
     * @return array
     */
    protected function mapRequestIds(array $queues): array
    {
        $map = [];
        foreach ($queues as $queue) {
            /** @var Request $request */
            foreach ($queue as $request) {
                $map[$request->getId()] = $request;
            }
        }

        return $map;
    }

    /**
     * Map requests meta data to Ids
     * @param BatchRequest $batchRequest
     * @return array|RequestConfig[]
     * @throws SerializationException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function mergeConfigsPerRequest(BatchRequest $batchRequest): array
    {
        $appConfig = $this->getMasterConfig();
        $requestsMap = $this->mapRequestIds($batchRequest->getQueues());
        $map = [];
        /** @var Request $request */
        foreach ($requestsMap as $id => $request) {
            // update config with merged one
            $map[$id] = $request->setConfig($this->buildRequestConfig($request, $batchRequest, $appConfig));
        }

        return $map;
    }

    /**
     * @param ChannelInterface $channel
     * @param array|Request[] $requests mapped requests by ids
     * @return array
     * @throws SerializationException
     */
    protected function buildResponses(ChannelInterface $channel, array $requests): array
    {
        $succeededResp = [];
        $failedResp = [];

        // TODO: check expected status codes and split responses to good and bad

        foreach ($channel->getOkResponses() as $id => $srcResponse) {
            // TODO: dispatcher extend with config request resulting object
            // TODO: add headers from config

            $requestConfig = $requests[$id]->getConfig();

            $targetResponse = new Response();
            $targetResponse->setId($id);
            $targetResponse->setStatusCode($srcResponse->getStatusCode());
            $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));

            $this->genResponseBody($requestConfig->getFormat(), $srcResponse, $targetResponse);

            if ($this->dispatcher) {
                $event = new BuildResponseEvent($targetResponse, $srcResponse, $requestConfig);
                $this->dispatcher->dispatch(BuildResponseEvent::EVENT_POST_BUILD, $event);
                $targetResponse = $event->getTargetResponse();
                $requestConfig = $event->getRequestConfig();
            }

            if (!$requestConfig->getSilent()) {
                $succeededResp[$targetResponse->getId()] = $targetResponse;
            }
        }

        foreach ($channel->getFailedResponses() as $id => $srcResponse) {
            $requestConfig = $requests[$id]->getConfig();

            $targetResponse = new Response();
            $targetResponse->setId($id);
            $targetResponse->setStatusCode($srcResponse->getStatusCode());
            $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));

            $this->genResponseBody($requestConfig->getFormat(), $srcResponse, $targetResponse);

            if ($this->dispatcher) {
                $event = new BuildResponseEvent($targetResponse, $srcResponse, $requestConfig);
                $this->dispatcher->dispatch(BuildResponseEvent::EVENT_FAILED_POST_BUILD, $event);
                $targetResponse = $event->getTargetResponse();
            }

            $failedResp[$targetResponse->getId()] = $targetResponse;
        }

        return [$succeededResp, $failedResp];
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
     * @param Response $targetResponse
     * @throws SerializationException
     */
    protected function genResponseBody($format, ResponseInterface $srcResponse, Response $targetResponse): void
    {
        switch ($format) {
            case Response::FORMAT_JSON:
                // TODO: if exception thrown then somehow mark response as failed and write some error info
                $data = $srcResponse->getBody()->getContents();
                if ($data === '' || $data === null) {
                    $targetResponse->setData(null);
                } else {
                    try {
                        $targetResponse->setData((new JsonDecode())
                            ->decode($data,'json', ['json_decode_associative' => true]));
                    } catch (NotEncodableValueException $e) {
                        // TODO: add event dispatcher, if defined
                        $targetResponse->setData($data); // set raw data
                    }
                }
                break;
            case Response::FORMAT_TEXT:
                $targetResponse->setData($srcResponse->getBody()->getContents());
                break;
            case Response::FORMAT_BINARY:
                // TODO: implement me! Download files to tmp dir and return links to those files
                // TODO: implement FileStorageInterface to abstract place for storing files
            default:
                throw new SerializationException('Not supported format: ' . $format);
        }
    }

    /**
     * @param Request $request
     * @param BatchRequest $batchRequest
     * @param array $appConfig
     * @return RequestConfig
     * @throws SerializationException
     */
    protected function buildRequestConfig(Request $request, BatchRequest $batchRequest, array $appConfig)
    {
        $requestCfg = $request->getConfig();
        $generalCfg = $batchRequest->getConfig();

        if (!$generalCfg) {
            if ($requestCfg) {
                return $this->mergeRequestConfigDefaults($appConfig, $requestCfg);
            }

            return $this->genDefaultConfig($appConfig);
        }

        if ($requestCfg) {
            $cfgMergeStrategy = $requestCfg->getConfigMerge() ?? $generalCfg->getConfigMerge() ??
                $appConfig['config_merge'];

            switch ($cfgMergeStrategy) {
                case RequestConfig::CONFIG_MERGE_FIRST: return $this->mergeRequestConfigDefaults($appConfig, $requestCfg);
                case RequestConfig::CONFIG_MERGE_UNIQUE:
                    return $this->mergeRequestConfigScopes($appConfig, $requestCfg, $generalCfg, $cfgMergeStrategy);
                case RequestConfig::CONFIG_MERGE_IGNORE: return $this->genDefaultConfig($appConfig);
                default:
                    throw new SerializationException('Unexpected config merge strategy type: ' .
                        $cfgMergeStrategy
                    );
            }
        } else {
            return clone $generalCfg;
        }
    }

    /**
     * take all default values from app config
     * @param array $appConfig
     * @return RequestConfig
     */
    protected function genDefaultConfig(array $appConfig): RequestConfig
    {
        $newConfig = new RequestConfig();
        $newConfig->setFormat($appConfig['resource_format']);
        $newConfig->setSilent($appConfig['silent']);
        $newConfig->setOnFail($appConfig['on_fail']);
        $newConfig->setConfigMerge($appConfig['config_merge']);

        return $newConfig;
    }

    /**
     * @param array $appConfig
     * @param RequestConfig|null $requestCfg
     * @param RequestConfig|null $generalCfg
     * @param string|null $cfgMergeStrategy
     * @return RequestConfig
     * @throws SerializationException
     */
    protected function mergeRequestConfigScopes(array $appConfig, ?RequestConfig $requestCfg, ?RequestConfig $generalCfg, ?string $cfgMergeStrategy): RequestConfig
    {
        $newConfig = new RequestConfig();

        $newConfig->setFormat($requestCfg->getFormat() ??
            $generalCfg->getFormat() ?? $appConfig['resource_format']);

        $newConfig->setSilent($requestCfg->getSilent() ?? $generalCfg->getSilent() ?? $appConfig['silent']);

        $newConfig->setOnFail($requestCfg->getOnFail() ??
            $generalCfg->getOnFail() ?? $appConfig['on_fail']);

        $newConfig->setConfigMerge($cfgMergeStrategy);

        return $newConfig;
    }

    /**
     * @param array $appConfig
     * @param RequestConfig|null $requestCfg
     * @return RequestConfig
     * @throws SerializationException
     */
    protected function mergeRequestConfigDefaults(array $appConfig, RequestConfig $requestCfg): RequestConfig
    {
        $newConfig = new RequestConfig();

        $newConfig->setFormat($requestCfg->getFormat() ?? $appConfig['resource_format']);
        $newConfig->setSilent($appConfig['silent'] ?: $requestCfg->getSilent());
        $newConfig->setOnFail($requestCfg->getOnFail() ?? $appConfig['on_fail']);
        $newConfig->setConfigMerge($requestCfg->getConfigMerge() ?? $appConfig['config_merge']);

        return $newConfig;
    }

    /**
     * @param BatchRequest $batchRequest
     * @param ChannelInterface $channel
     * @param bool $aborted
     */
    protected function dispatchSend(BatchRequest $batchRequest, ChannelInterface $channel, bool &$aborted = false)
    {
        try {
            $channel->send($batchRequest);
        } catch (ChannelException $e) {
            $aborted = true;
        }
    }
}