<?php

namespace Deliveryman\Service;


use Deliveryman\ClientProvider\ClientProviderInterface;
use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\EventListener\EventSender;
use Deliveryman\Exception\SendingException;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
     * @var EventDispatcher|null
     */
    protected $dispatcher;

    /**
     * Sender constructor.
     * @param ClientProviderInterface $clientProvider
     * @param EventDispatcher|null $dispatcher
     */
    public function __construct(ClientProviderInterface $clientProvider, ?EventDispatcher $dispatcher = null)
    {
        $this->clientProvider = $clientProvider;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Process batch request queries
     * @param BatchRequest $batchRequest
     * @return BatchResponse
     * @throws SendingException
     */
    public function send(BatchRequest $batchRequest)
    {
        // 0. check global config settings
        // 1. loop through queues to send requests in parallel
        // 2. merge configs according to settings per request
        // 3. create queues and process them accordingly
        // 4. dispatch events per requests, queues etc.

        $this->clientProvider->clearErrors();

        if (!$batchRequest->getQueues()) {
            throw new SendingException('No queues with requests specified to process.');
        }

        if (!$this->dispatcher) {
            $responses = $this->clientProvider->sendQueues($batchRequest->getQueues());
        } else {
            $event = (new EventSender())
                ->setBatchRequest($batchRequest)
                ->setClientProvider($this->clientProvider)
            ;
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_PRE_SEND, $event);
            $batchRequest = $event->getBatchRequest();     // batch request can be changed
            $clientProvider = $event->getClientProvider(); // client provider may be redefined on-fly

            $responses = $clientProvider->sendQueues($batchRequest->getQueues());

            $event->setResponses($responses);
            $this->dispatcher->dispatch(EventSender::EVENT_SENDER_POST_SEND, $event);
            $responses = $event->getResponses();           // responses switch
        }

        return $responses;
    }

    /**
     * Return true if errors found during last send batch request
     * @return bool
     */
    public function hasErrors()
    {
        return $this->clientProvider->hasErrors();
    }

    /**
     * Get all recent errors
     * @return array
     */
    public function getErrors()
    {
        return $this->clientProvider->getErrors();
    }
}