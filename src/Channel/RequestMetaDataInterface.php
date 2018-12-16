<?php

namespace Deliveryman\Channel;


use Deliveryman\Entity\RequestConfig;

interface RequestMetaDataInterface
{
    /**
     * Set identifier of request
     * @param $id
     * @return mixed
     */
    public function setId($id);

    /**
     * Get ID of request
     * @return mixed
     */
    public function getId();

    /**
     * Set request config
     * @param RequestConfig|null $requestConfig
     * @return mixed
     */
    public function setRequestConfig(?RequestConfig $requestConfig);

    /**
     * Get request config
     * @return RequestConfig
     */
    public function getRequestConfig();
}