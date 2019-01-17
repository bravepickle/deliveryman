<?php

namespace Deliveryman\EventListener;

use Deliveryman\Channel\ChannelInterface;
use Deliveryman\Entity\BatchRequest;
use Symfony\Component\EventDispatcher\Event as BasicEvent;

class EventSender extends BasicEvent
{
    /**
     * Is called before sender sends data for processing inside client provider
     */
    const EVENT_SENDER_PRE_SEND = 'deliveryman.sender.pre_send';

    /**
     * Is called before sender sends data for processing inside client provider
     */
    const EVENT_SENDER_POST_SEND = 'deliveryman.sender.post_send';

    /**
     * @var BatchRequest|null
     */
    protected $batchRequest;

    /**
     * @var ChannelInterface|null
     */
    protected $channel;

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
     * @return ChannelInterface|null
     */
    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    /**
     * @param ChannelInterface|null $channel
     * @return EventSender
     */
    public function setChannel(?ChannelInterface $channel): EventSender
    {
        $this->channel = $channel;

        return $this;
    }

}