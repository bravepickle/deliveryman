<?php

namespace Deliveryman\Service;


use Deliveryman\Channel\ChannelInterface;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\EventListener\EventSender;
use Deliveryman\Exception\ChannelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class BatchRequestHandler
 * Is a facade for sending parsed batch request
 * @package Deliveryman\Service
 */
class BatchRequestHandler implements BatchRequestHandlerInterface
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
     * BatchRequestHandler constructor.
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
     * @inheritdoc
     */
    public function __invoke(BatchRequest $batchRequest): BatchResponse
    {
        $this->channel->clear();
        $channel = $this->channel;
        $errors = $this->validator->validate($batchRequest, ['Default', $this->channel->getName()]);
        if (!empty($errors)) {
            return $this->wrapErrors($errors);
        }

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

        return $this->wrapResponses($channel, $aborted);
    }

    /**
     * @param ChannelInterface $channel
     * @param bool $aborted
     * @return BatchResponse
     */
    protected function wrapResponses(
        ChannelInterface $channel,
        bool $aborted
    ): BatchResponse
    {
        list($okResp, $failResp) = $this->buildResponses($channel);

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
     * @param ChannelInterface $channel
     * @return array
     */
    protected function buildResponses(ChannelInterface $channel): array
    {
        $succeededResp = [];
        $failedResp = [];

        foreach ($channel->getOkResponses() as $id => $srcResponse) {
            $succeededResp[$srcResponse->getId()] = $srcResponse;
        }

        foreach ($channel->getFailedResponses() as $id => $srcResponse) {
            $failedResp[$srcResponse->getId()] = $srcResponse;
        }

        return [$succeededResp, $failedResp];
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