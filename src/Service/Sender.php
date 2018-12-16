<?php

namespace Deliveryman\Service;


use Deliveryman\ClientProvider\ClientProviderInterface;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Entity\Response;
use Deliveryman\EventListener\BuildResponseEvent;
use Deliveryman\EventListener\EventSender;
use Deliveryman\Exception\SendingException;
use Deliveryman\Exception\SerializationException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Class Sender
 * Is a facade for sending parsed batch request
 * @package Deliveryman\Service
 */
class Sender
{
    /**
     * @var ClientProviderInterface
     */
    protected $clientProvider;

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
     * @param ClientProviderInterface $clientProvider
     * @param ConfigManager $configManager
     * @param BatchRequestValidator $batchRequestValidator
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(
        ClientProviderInterface $clientProvider,
        ConfigManager $configManager,
        BatchRequestValidator $batchRequestValidator,
        ?EventDispatcherInterface $dispatcher = null
    )
    {
        $this->clientProvider = $clientProvider;
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

        $this->clientProvider->clearErrors();

        if (!$batchRequest->getQueues()) {
            throw new SendingException('No queues with requests specified to process.');
        }

        $clientProvider = $this->clientProvider;

        $errors = $this->validator->validate($batchRequest);
        if (!empty($errors)) {
            return $this->wrapErrors($errors);
        }

        if (!$this->dispatcher) {
            $responses = $clientProvider->send($batchRequest->getQueues());
        } else {
            $event = (new EventSender())
                ->setBatchRequest($batchRequest)
                ->setClientProvider($clientProvider);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_PRE_SEND, $event);
            $batchRequest = $event->getBatchRequest();     // batch request can be changed
            $clientProvider = $event->getClientProvider(); // client provider may be redefined on-fly

            $responses = $clientProvider->send($batchRequest->getQueues());

            $event->setResponses($responses);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_POST_SEND, $event);
            $responses = $event->getResponses();           // responses switch
            $clientProvider = $event->getClientProvider();
        }

        return $this->wrapResponses($clientProvider, $responses, $batchRequest);
    }

    /**
     * @param ClientProviderInterface $clientProvider
     * @param array|ResponseInterface[] $responses
     * @param BatchRequest $batchRequest
     * @return BatchResponse
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws SerializationException
     */
    protected function wrapResponses(
        ClientProviderInterface $clientProvider,
        array $responses,
        BatchRequest $batchRequest
    ): BatchResponse
    {
        $config = $this->getMasterConfig();

        $batchResponse = new BatchResponse();

        if (empty($config['silent']) && $responses) {
            list($okResp, $failResp) = $this->buildResponses($responses, $batchRequest);

            if ($okResp) {
                $batchResponse->setData($okResp);
            }

            if ($failResp) {
                $batchResponse->setErrors($failResp);
            }
        }

        if ($clientProvider->hasErrors()) {
            $batchResponse->setStatus(BatchResponse::STATUS_FAILED);
            // TODO: format before sending to client???
            $batchResponse->setErrors($clientProvider->getErrors());
        } else {
            $batchResponse->setStatus(BatchResponse::STATUS_SUCCESS);
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
        foreach ($requestsMap as $request) {
            $map[$request->getId()] = $this->buildRequestConfig($request, $batchRequest, $appConfig);
        }

        return $map;
    }

    /**
     * @param array|ResponseInterface[] $responses
     * @param BatchRequest $batchRequest
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws SerializationException
     */
    protected function buildResponses(array $responses, BatchRequest $batchRequest): array
    {
        $cfgMap = $this->mergeConfigsPerRequest($batchRequest);
        $succeededResp = [];
        $failedResp = [];

        // TODO: check expected status codes and split responses to good and bad

        foreach ($responses as $id => $srcResponse) {
            // TODO: dispatcher extend with config request resulting object
            // TODO: add headers from config

            $requestConfig = $cfgMap[$id];

            $targetResponse = new Response();
            $targetResponse->setId($id);
            $targetResponse->setStatusCode($srcResponse->getStatusCode());

            if ($requestConfig->getHeaders()) {
                $targetResponse->setHeaders(array_merge(
                    $requestConfig->getHeaders(), $this->buildResponseHeaders($srcResponse)
                ));
            } else {
                $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));
            }

            $this->genResponseBody($requestConfig->getFormat(), $srcResponse, $targetResponse);

            if ($this->dispatcher) {
                $event = new BuildResponseEvent($targetResponse, $srcResponse, $requestConfig);
                $this->dispatcher->dispatch(BuildResponseEvent::EVENT_POST_BUILD, $event);
                $targetResponse = $event->getTargetResponse();
                $requestConfig = $event->getRequestConfig();
            }

            if (in_array($srcResponse->getStatusCode(), $requestConfig->getExpectedStatusCodes())) {
                $succeededResp[$targetResponse->getId()] = $targetResponse;
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
                $targetResponse->setData((new JsonEncoder())->decode(
                    $srcResponse->getBody()->getContents(),
                    'json'
                ));
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
                return clone $requestCfg;
            }

            return $this->genDefaultConfig($appConfig);
        }

        if ($requestCfg) {
            $cfgMergeStrategy = $requestCfg->getConfigMerge() ?? $generalCfg->getConfigMerge() ??
                $appConfig['configMerge'];

            switch ($cfgMergeStrategy) {
                case RequestConfig::CONFIG_MERGE_FIRST: return clone $requestCfg; // do nothing. Already is set
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
        if (!empty($appConfig['headers'])) {
            foreach ($appConfig['headers'] as $header) {
                $headers[] = new RequestHeader($header['name'], $header['value']);
            }
        }

        if (!$requestCfg->getHeaders()) {
            $headers = array_merge($headers, $generalCfg->getHeaders());
        } elseif (!$generalCfg->getHeaders()) {
            $headers = array_merge($headers, $requestCfg->getHeaders());
        } else { // merge
            $foundHeaders = [];
            foreach ($requestCfg->getHeaders() as $header) {
                $foundHeaders[] = $header->getName();
                $headers[] = clone $header;
            }

            foreach ($generalCfg->getHeaders() as $genHeader) {
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

        if (!$requestCfg->getExpectedStatusCodes()) {
            $newConfig->setExpectedStatusCodes($generalCfg->getExpectedStatusCodes()); // nothing to merge
        } elseif (!$generalCfg->getExpectedStatusCodes()) {
            $newConfig->setExpectedStatusCodes($requestCfg->getExpectedStatusCodes()); // nothing to merge
        } else { // merge
            $statusCodes = array_values(array_unique(array_merge(
                $newConfig->getExpectedStatusCodes(), $generalCfg->getExpectedStatusCodes()
            )));

            $newConfig->setExpectedStatusCodes($statusCodes);
        }

        if (!$newConfig->getExpectedStatusCodes()) {
            $newConfig->setExpectedStatusCodes($appConfig['expectedStatusCodes']); // none were set in batch
        }
    }

    /**
     * @param array $appConfig
     * @return RequestConfig
     */
    protected function genDefaultConfig(array $appConfig): RequestConfig
    {
// take all default values from app config
        $newConfig = new RequestConfig();
        $newConfig->setFormat($appConfig['resourceFormat']);
        $newConfig->setSilent($appConfig['silent']);
        $newConfig->setOnFail($appConfig['onFail']);
        $newConfig->setConfigMerge($appConfig['configMerge']);
        $newConfig->setHeaders($appConfig['headers']);
        $newConfig->setExpectedStatusCodes($appConfig['expectedStatusCodes']);

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
            $generalCfg->getFormat() ?? $appConfig['resourceFormat']);

        $newConfig->setSilent($appConfig['silent'] ?:
            $requestCfg->getSilent() ?? $generalCfg->getSilent());

        $newConfig->setOnFail($requestCfg->getOnFail() ??
            $generalCfg->getOnFail() ?? $appConfig['onFail']);

        $newConfig->setConfigMerge($cfgMergeStrategy);

        $this->mergeHeaders($newConfig, $requestCfg, $generalCfg, $appConfig);
        $this->mergeExpectedStatusCodes($newConfig, $requestCfg, $generalCfg, $appConfig);

        return $newConfig;
    }
}