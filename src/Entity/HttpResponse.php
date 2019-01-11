<?php

namespace Deliveryman\Entity;

use Deliveryman\Entity\HttpGraph\HttpHeader;
use Deliveryman\Normalizer\NormalizableInterface;

class HttpResponse implements NormalizableInterface, ResponseItemInterface
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
     * @var HttpHeader[]|null|array
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
     * @return HttpResponse
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return array|HttpHeader[]|null
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array|HttpHeader[]|null $headers
     * @return HttpResponse
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
     * @return HttpResponse
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
     * @return HttpResponse
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

}