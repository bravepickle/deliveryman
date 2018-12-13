<?php

namespace Deliveryman\Entity;

/**
 * Class BatchRequest
 * This is main data object that contains all data passed in body for batch request
 * @package Deliveryman\Entity
 */
class BatchRequest
{
    /**
     * General request config
     * @var RequestConfig
     */
    protected $config;

    /**
     * Array of arrays that represent requests queue sequence
     * with multiple requests
     * @var array|null
     */
    protected $queues;

    /**
     * @return RequestConfig
     */
    public function getConfig(): RequestConfig
    {
        return $this->config;
    }

    /**
     * @param RequestConfig $config
     * @return BatchRequest
     */
    public function setConfig(RequestConfig $config): BatchRequest
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getQueues(): ?array
    {
        return $this->queues;
    }

    /**
     * @param array|null $queues
     * @return BatchRequest
     */
    public function setQueues(?array $queues): BatchRequest
    {
        $this->queues = $queues;

        return $this;
    }

}