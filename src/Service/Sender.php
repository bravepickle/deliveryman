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

        $this->channel->clearErrors();

        if (!$batchRequest->getQueues()) {
            throw new SendingException('No queues with requests specified to process.');
        }

        $channel = $this->channel;

        $errors = $this->validator->validate($batchRequest);
        if (!empty($errors)) {
            return $this->wrapErrors($errors);
        }

        $aborted = false;
        if (!$this->dispatcher) {
            $responses = $this->dispatchSend($batchRequest, $channel, $aborted);
        } else {
            $event = (new EventSender())
                ->setBatchRequest($batchRequest)
                ->setChannel($channel);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_PRE_SEND, $event);
            $batchRequest = $event->getBatchRequest();     // batch request can be changed
            $channel = $event->getChannel(); // client provider may be redefined on-fly

            $responses = $this->dispatchSend($batchRequest, $channel, $aborted);

            $event->setResponses($responses);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_POST_SEND, $event);
            $responses = $event->getResponses();           // responses switch
            $channel = $event->getChannel();
        }

        return $this->wrapResponses($channel, $responses, $batchRequest, $aborted);
    }

    /**
     * @param ChannelInterface $channel
     * @param array|ResponseInterface[] $responses
     * @param BatchRequest $batchRequest
     * @param bool $aborted
     * @return BatchResponse
     * @throws SerializationException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function wrapResponses(
        ChannelInterface $channel,
        array $responses,
        BatchRequest $batchRequest,
        bool $aborted
    ): BatchResponse
    {
        $batchResponse = new BatchResponse();
        if ($aborted) {
            $batchResponse->setStatus(BatchResponse::STATUS_ABORTED);
        } else {
            if ($channel->hasErrors()) {
                $batchResponse->setStatus(BatchResponse::STATUS_FAILED);
                // TODO: format before sending to client???
                $batchResponse->setErrors($channel->getErrors());
            } else {
                $batchResponse->setStatus(BatchResponse::STATUS_SUCCESS);
            }
        }

        if ($responses) {
            $requests = $this->mergeConfigsPerRequest($batchRequest);
            list($okResp, $failResp) = $this->buildResponses($responses, $requests);

            if ($okResp) {
                $batchResponse->setData($okResp);
            }

            if ($failResp) {
                $batchResponse->setStatus(BatchResponse::STATUS_FAILED);
                $batchResponse->setErrors((array)$batchResponse->getErrors() + $failResp);
            }
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
            $map[$id] = clone $request->setConfig($this->buildRequestConfig($request, $batchRequest, $appConfig));
        }

        return $map;
    }

    /**
     * @param array|ResponseInterface[] $responses
     * @param array|Request[] $requests mapped requests by ids
     * @return array
     * @throws SerializationException
     */
    protected function buildResponses(array $responses, array $requests): array
    {
        $succeededResp = [];
        $failedResp = [];

        // TODO: check expected status codes and split responses to good and bad

        foreach ($responses as $id => $srcResponse) {
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

            if (in_array($srcResponse->getStatusCode(), $requestConfig->getExpectedStatusCodes())) {
                if (!$requestConfig->getSilent()) {
                    $succeededResp[$targetResponse->getId()] = $targetResponse;
                }
            } else {
                $failedResp[$targetResponse->getId()] = $targetResponse;
            }
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
                        $targetResponse->setData((new JsonDecode([JsonDecode::ASSOCIATIVE => true]))
                            ->decode($data,'json'));
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
     * @param RequestConfig $newConfig
     * @param RequestConfig|null $requestCfg
     * @param RequestConfig|null $generalCfg
     * @param array $appConfig
     */
    protected function mergeHeaders(RequestConfig $newConfig, ?RequestConfig $requestCfg, ?RequestConfig $generalCfg, array $appConfig): void
    {
        $headers = [];
        $generalHeaders = $generalCfg && $generalCfg->getHeaders() ? $generalCfg->getHeaders() : [];
        $reqHeaders = $requestCfg && $requestCfg->getHeaders() ? $requestCfg->getHeaders() : [];

        if (!$reqHeaders) {
            $headers = $generalHeaders;
        } elseif ($generalHeaders) {
            $foundHeaders = [];
            foreach ($reqHeaders as $header) {
                $foundHeaders[] = $header->getName();
                $headers[] = clone $header;
            }

            foreach ($generalHeaders as $genHeader) {
                if (!in_array($genHeader->getName(), $foundHeaders)) {
                    $headers[] = clone $genHeader; // only not set headers are added
                }
            }
        }

        $newConfig->setHeaders($headers);
    }

    /**
     * @param RequestConfig $newConfig
     * @param RequestConfig|null $requestCfg
     * @param RequestConfig|null $generalCfg
     * @param array $appConfig
     */
    protected function mergeExpectedStatusCodes(RequestConfig $newConfig, ?RequestConfig $requestCfg, ?RequestConfig $generalCfg, array $appConfig): void
    {
        // TODO: set status codes from app config if not set here

        $reqExpectedStatusCodes = $requestCfg && $requestCfg->getExpectedStatusCodes() ?
            $requestCfg->getExpectedStatusCodes() : [];
        $genExpectedStatusCodes = $generalCfg && $generalCfg->getExpectedStatusCodes() ?
            $generalCfg->getExpectedStatusCodes() : [];

        if (!$reqExpectedStatusCodes) {
            $newConfig->setExpectedStatusCodes($genExpectedStatusCodes); // nothing to merge
        } elseif (!$genExpectedStatusCodes) {
            $newConfig->setExpectedStatusCodes($reqExpectedStatusCodes); // nothing to merge
        } else { // merge
            $statusCodes = array_values(array_unique(array_merge(
                $newConfig->getExpectedStatusCodes(), $genExpectedStatusCodes
            )));

            $newConfig->setExpectedStatusCodes($statusCodes);
        }

        if (!$newConfig->getExpectedStatusCodes()) {
            $newConfig->setExpectedStatusCodes($appConfig['expected_status_codes']); // none were set in batch
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
        $newConfig->setExpectedStatusCodes($appConfig['expected_status_codes']);

        return $newConfig;
    }

    /**
     * @param array $appConfig
     * @param RequestConfig|null $requestCfg
     * @param RequestConfig|null $generalCfg
     * @param string|null $cfgMergeStrategy
     * @return RequestConfig
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

        $this->mergeHeaders($newConfig, $requestCfg, $generalCfg, $appConfig);
        $this->mergeExpectedStatusCodes($newConfig, $requestCfg, $generalCfg, $appConfig);

        return $newConfig;
    }

    /**
     * @param array $appConfig
     * @param RequestConfig|null $requestCfg
     * @return RequestConfig
     */
    protected function mergeRequestConfigDefaults(array $appConfig, RequestConfig $requestCfg): RequestConfig
    {
        $newConfig = new RequestConfig();

        $newConfig->setFormat($requestCfg->getFormat() ?? $appConfig['resource_format']);
        $newConfig->setSilent($appConfig['silent'] ?: $requestCfg->getSilent());
        $newConfig->setOnFail($requestCfg->getOnFail() ?? $appConfig['on_fail']);
        $newConfig->setConfigMerge($requestCfg->getConfigMerge() ?? $appConfig['config_merge']);

        $this->mergeHeaders($newConfig, $requestCfg, null, $appConfig);
        $this->mergeExpectedStatusCodes($newConfig, $requestCfg, null, $appConfig);

        return $newConfig;
    }

    /**
     * @param BatchRequest $batchRequest
     * @param ChannelInterface $channel
     * @param bool $aborted
     * @return array|ResponseInterface[]|null
     */
    protected function dispatchSend(BatchRequest $batchRequest, ChannelInterface $channel, bool &$aborted = false)
    {
        try {
            return $channel->send($batchRequest->getQueues());
        } catch (ChannelException $e) {
            $aborted = true;
            return $e->getResponses();
        }
    }
}