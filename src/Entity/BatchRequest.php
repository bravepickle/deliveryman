<?php

namespace Deliveryman\Entity;

use Deliveryman\Normalizer\NormalizableInterface;

/**
 * Class BatchRequest
 * This is main data object that contains all data passed in body for batch request
 * @package Deliveryman\Entity
 */
class BatchRequest implements NormalizableInterface
{
    /**
     * General request config
     * @var RequestConfig|null
     */
    protected $config;

    /**
     * Container for batch request data
     * @var array|null
     */
    protected $data;

    /**
     * @return RequestConfig
     */
    public function getConfig(): ?RequestConfig
    {
        return $this->config;
    }

    /**
     * @param RequestConfig|null $config
     * @return BatchRequest
     */
    public function setConfig(?RequestConfig $config): BatchRequest
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array|null $data
     * @return BatchRequest
     */
    public function setData(?array $data): BatchRequest
    {
        $this->data = $data;

        return $this;
    }

}