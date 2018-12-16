<?php

namespace Deliveryman\EventListener;

use Deliveryman\Channel\ChannelInterface;
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
     * @var ChannelInterface|null
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
     * @return ChannelInterface|null
     */
    public function getClientProvider(): ?ChannelInterface
    {
        return $this->clientProvider;
    }

    /**
     * @param ChannelInterface|null $clientProvider
     * @return EventSender
     */
    public function setClientProvider(?ChannelInterface $clientProvider): EventSender
    {
        $this->clientProvider = $clientProvider;

        return $this;
    }

}