<?php

namespace Deliveryman\EventListener;

use Symfony\Component\EventDispatcher\Event as BasicEvent;

class Event extends BasicEvent
{
    /**
     * Executed before cache items being saved
     */
    const EVENT_PRE_SAVE = 'deliveryman.cache.pre_save';

    /**
     * @var mixed
     */
    protected $data;

    /**
     * Event constructor.
     * @param mixed $data
     */
    public function __construct($data = null)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return Event
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

}