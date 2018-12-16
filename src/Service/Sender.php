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
            $batchResponse->setData($this->buildResponses($responses, $batchRequest));
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
     * @param string $defaultFormat
     * @return array
     * @throws SerializationException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function mapMetaRequestIds(BatchRequest $batchRequest, string $defaultFormat): array
    {
        $requestsMap = $this->mapRequestIds($batchRequest->getQueues());
        $map = [];
        /** @var Request $request */
        foreach ($requestsMap as $request) {
            $requestConfig = $this->buildRequestConfig($request, $batchRequest);

            if ($requestConfig && $requestConfig->getFormat()) {
                $map[$request->getId()]['format'] = $requestConfig->getFormat();
            } else {
                $map[$request->getId()]['format'] = $defaultFormat;
            }

            if ($requestConfig && $requestConfig->getFormat()) {
                $map[$request->getId()]['format'] = $requestConfig->getFormat();
            } else {
                $map[$request->getId()]['format'] = $defaultFormat;
            }
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
        $defaultFormat = $this->getMasterConfig()['resourceFormat'];
        $metaMap = $this->mapMetaRequestIds($batchRequest, $defaultFormat);
        $succeededResp = [];
        $failedResp = [];

        // TODO: check expected status codes and split responses to good and bad

        foreach ($responses as $id => $srcResponse) {
            $targetResponse = new Response();
            $targetResponse->setId($id);
            $targetResponse->setStatusCode($srcResponse->getStatusCode());
            $targetResponse->setHeaders($this->buildResponseHeaders($srcResponse));

            $this->genResponseBody($metaMap[$id]['format'], $srcResponse, $targetResponse);

            if ($this->dispatcher) {
                $event = new BuildResponseEvent($targetResponse, $srcResponse);
                $this->dispatcher->dispatch(BuildResponseEvent::EVENT_POST_BUILD, $event);
                $targetResponse = $event->getTargetResponse();
            }

            // TODO: merge configs strategy

//            $srcResponse->
//
//            if ($srcResponse->getStatusCode()) {
//
//            }

            $succeededResp[$targetResponse->getId()] = $targetResponse;
        }

        return $succeededResp;
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
     * @return RequestConfig|null
     * @throws SerializationException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function buildRequestConfig(Request $request, BatchRequest $batchRequest)
    {
        $appConfig = $this->getMasterConfig();
        $requestCfg = $request->getConfig();
        $generalCfg = $batchRequest->getConfig();

        if (!$generalCfg) {
            return $requestCfg ? clone $requestCfg : null; // nothing to merge, avoid affecting initial configs
        }

        if ($requestCfg) {
            $cfgMergeStrategy = $requestCfg->getConfigMerge() ?? $generalCfg->getConfigMerge();
            if (!$cfgMergeStrategy) {
                $cfgMergeStrategy = $appConfig['configMerge'];
            }

            switch ($cfgMergeStrategy) {
                case RequestConfig::CONFIG_MERGE_FIRST:
                    return clone $requestCfg; // do nothing. Already is set

                case RequestConfig::CONFIG_MERGE_UNIQUE:
                    $newConfig = new RequestConfig();

                    $newConfig->setFormat($requestCfg->getFormat() ?? $generalCfg->getFormat());
                    $newConfig->setSilent($requestCfg->getSilent() ?? $generalCfg->getSilent());
                    $newConfig->setOnFail($requestCfg->getOnFail() ?? $generalCfg->getOnFail());
                    $newConfig->setConfigMerge($requestCfg->getConfigMerge() ?? $generalCfg->getConfigMerge());

                    $this->mergeHeaders($newConfig, $requestCfg, $generalCfg);
                    $this->mergeExpectedStatusCodes($newConfig, $requestCfg, $generalCfg);

                    return $newConfig;

                case RequestConfig::CONFIG_MERGE_IGNORE:
                    return null; // ignoring all data, even if set. Look into app config only

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
     */
    protected function mergeHeaders(RequestConfig $newConfig, ?RequestConfig $requestCfg, ?RequestConfig $generalCfg): void
    {
        if (!$requestCfg->getHeaders()) {
            $newConfig->setHeaders($generalCfg->getHeaders()); // nothing to merge
        } elseif (!$generalCfg->getHeaders()) {
            $newConfig->setHeaders($requestCfg->getHeaders()); // nothing to merge
        } else { // merge
            $headers = [];
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

            $newConfig->setHeaders($headers);
        }
    }

    /**
     * @param RequestConfig $newConfig
     * @param RequestConfig|null $requestCfg
     * @param RequestConfig|null $generalCfg
     */
    protected function mergeExpectedStatusCodes(RequestConfig $newConfig, ?RequestConfig $requestCfg, ?RequestConfig $generalCfg): void
    {
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
    }
}