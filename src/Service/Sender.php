<?php

namespace Deliveryman\Service;


use Deliveryman\ClientProvider\ClientProviderInterface;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\Response;
use Deliveryman\EventListener\BuildResponseEvent;
use Deliveryman\EventListener\EventSender;
use Deliveryman\Exception\SendingException;
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
    ) {
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
            $responses = $clientProvider->sendQueues($batchRequest->getQueues());
        } else {
            $event = (new EventSender())
                ->setBatchRequest($batchRequest)
                ->setClientProvider($clientProvider);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_PRE_SEND, $event);
            $batchRequest = $event->getBatchRequest();     // batch request can be changed
            $clientProvider = $event->getClientProvider(); // client provider may be redefined on-fly

            $responses = $clientProvider->sendQueues($batchRequest->getQueues());

            $event->setResponses($responses);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_POST_SEND, $event);
            $responses = $event->getResponses();           // responses switch
            $clientProvider = $event->getClientProvider();
        }

        return $this->wrapResponses($clientProvider, $responses);
    }

    /**
     * @param ClientProviderInterface $clientProvider
     * @param array|ResponseInterface[] $responses
     * @return BatchResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function wrapResponses(
        ClientProviderInterface $clientProvider,
        array $responses
    ): BatchResponse {
        $config = $this->getMasterConfig();

        $batchResponse = new BatchResponse();

        if (empty($config['silent']) && $responses) {
            $batchResponse->setData($this->buildResponses($responses));
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
            ->setErrors($errors)
        ;

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
     * @param array $responses
     * @return array
     */
    protected function buildResponses(array $responses): array
    {
        $jsonDecoder = new JsonEncoder();
        $returnResponses = [];

        foreach ($responses as $id => $srcResponse) {
            // TODO: add event dispatcher with redefine data
            $targetResponse = new Response();
            $targetResponse->setId($id);
            $targetResponse->setStatusCode($srcResponse->getStatusCode());

            if ($srcResponse->hasHeader('Content-Type')) {
                $mime = strtolower($srcResponse->getHeader('Content-Type')[0]);

                if ($mime === 'application/json') {
                    $data = $jsonDecoder->decode($srcResponse->getBody()->getContents(), JsonEncoder::FORMAT);
                    $targetResponse->setData($data);
                }
            } else {
                $targetResponse->setData($srcResponse->getBody()->getContents());
            }

            if ($this->dispatcher) {
                $event = new BuildResponseEvent($targetResponse, $srcResponse);
                $this->dispatcher->dispatch(BuildResponseEvent::EVENT_POST_BUILD, $event);
                $targetResponse = $event->getTargetResponse();
            }

            $returnResponses[$targetResponse->getId()] = $targetResponse;
        }

        return $returnResponses;
    }
}