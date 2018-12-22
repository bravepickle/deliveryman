<?php

namespace Deliveryman\Entity\HttpQueue;


use Deliveryman\Entity\RequestHeader;
use Deliveryman\Entity\ResponseItemInterface;
use Deliveryman\Normalizer\NormalizableInterface;

class ResponseData implements NormalizableInterface, ResponseItemInterface
{
    const FORMAT_JSON = 'json';
    const FORMAT_TEXT = 'text';
    const FORMAT_BINARY = 'binary';

    /**
     * Identifier for given request for referencing aka alias
     * @var mixed
     */
    protected $id;

    /**
     * List of requests to send together with request with disregard to config merging strategy
     * @var RequestHeader[]|null|array
     */
    protected $headers;

    /**
     * @var mixed
     */
    protected $statusCode;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return ResponseData
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return array|RequestHeader[]|null
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array|RequestHeader[]|null $headers
     * @return ResponseData
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param mixed $statusCode
     * @return ResponseData
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
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
     * @return ResponseData
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

}