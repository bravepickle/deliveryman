<?php

namespace Deliveryman\Entity;


use Deliveryman\Normalizer\NormalizableInterface;

class Request implements NormalizableInterface, IdentifiableInterface
{
    /**
     * Identifier for given request for referencing aka alias
     * @var mixed
     */
    protected $id;

    /**
     * Target URI to send data to
     * @var string|null
     */
    protected $uri;

    /**
     * HTTP method to use for sending request
     * @var string|null
     */
    protected $method;

    /**
     * Configuration for given request
     * @var RequestConfig|null
     */
    protected $config;

    /**
     * List of requests to send together with request with disregard to config merging strategy
     * @var HttpHeader[]|null|array
     */
    protected $headers;

    /**
     * @var array|null
     */
    protected $query;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @param bool $guess generate ID if empty
     * @return mixed
     */
    public function getId($guess = true)
    {
        if ($this->id || !$guess) {
            return $this->id;
        }

        // Generate alias for request to identify this request
        if (!$this->getMethod()) {
            // guess method
            $id = $this->getData() ? 'POST' : 'GET';
        } else {
            $id = strtoupper($this->getMethod());
        }

        if ($this->getUri()) {
            $id .= '_' . $this->getUri();
        }

        return $id;
    }

    /**
     * @param mixed $id
     * @return Request
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @param string|null $uri
     * @return Request
     */
    public function setUri(?string $uri): Request
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * @param bool $guess guess method if empty
     * @return string|null
     */
    public function getMethod($guess = true): ?string
    {
        if ($this->method || !$guess) {
            return $this->method;
        }

        return $this->getData() ? 'POST' : 'GET';
    }

    /**
     * @param string|null $method
     * @return Request
     */
    public function setMethod(?string $method): Request
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return RequestConfig|null
     */
    public function getConfig(): ?RequestConfig
    {
        return $this->config;
    }

    /**
     * @param RequestConfig|null $config
     * @return Request
     */
    public function setConfig(?RequestConfig $config): Request
    {
        $this->config = $config;

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
     * @return Request
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getQuery(): ?array
    {
        return $this->query;
    }

    /**
     * @param array|null $query
     * @return Request
     */
    public function setQuery(?array $query): Request
    {
        $this->query = $query;

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
     * @return Request
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

}