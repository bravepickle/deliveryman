<?php

namespace Deliveryman\ClientProvider;


use Deliveryman\Entity\RequestConfig;

class RequestMetaData implements RequestMetaDataInterface
{
    /**
     * @var mixed
     */
    protected $id;

    /**
     * @var RequestConfig|null
     */
    protected $requestConfig;

    /**
     * @return RequestConfig
     */
    public function getRequestConfig(): RequestConfig
    {
        return $this->requestConfig;
    }

    /**
     * @param RequestConfig|null $requestConfig
     * @return RequestMetaData
     */
    public function setRequestConfig(?RequestConfig $requestConfig): RequestMetaData
    {
        $this->requestConfig = $requestConfig;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return RequestMetaData
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }
}