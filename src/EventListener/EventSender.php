<?php

namespace Deliveryman\EventListener;

use Deliveryman\ClientProvider\ClientProviderInterface;
use Deliveryman\Entity\BatchRequest;
use Symfony\Component\EventDispatcher\Event as BasicEvent;

class EventSender extends BasicEvent
{
    /**
     * Is called before sender sends queues for processing inside client provider
     */
    const EVENT_SENDER_PRE_SEND = 'deliveryman.sender.pre_send';

    /**
     * Is called before sender sends queues for processing inside client provider
     */
    const EVENT_SENDER_POST_SEND = 'deliveryman.sender.post_send';

    /**
     * @var BatchRequest|null
     */
    protected $batchRequest;

    /**
     * @var mixed
     */
    protected $responses;

    /**
     * @var ClientProviderInterface|null
     */
    protected $clientProvider;

    /**
     * @return BatchRequest|null
     */
    public function getBatchRequest(): ?BatchRequest
    {
        return $this->batchRequest;
    }

    /**
     * @param BatchRequest|null $batchRequest
     * @return EventSender
     */
    public function setBatchRequest(?BatchRequest $batchRequest): EventSender
    {
        $this->batchRequest = $batchRequest;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponses()
    {
        return $this->responses;
    }

    /**
     * @param mixed $responses
     * @return EventSender
     */
    public function setResponses($responses)
    {
        $this->responses = $responses;

        return $this;
    }

    /**
     * @return ClientProviderInterface|null
     */
    public function getClientProvider(): ?ClientProviderInterface
    {
        return $this->clientProvider;
    }

    /**
     * @param ClientProviderInterface|null $clientProvider
     * @return EventSender
     */
    public function setClientProvider(?ClientProviderInterface $clientProvider): EventSender
    {
        $this->clientProvider = $clientProvider;

        return $this;
    }

}