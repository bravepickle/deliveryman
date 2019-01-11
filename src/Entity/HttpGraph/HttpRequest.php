<?php
/**
 * Date: 2018-12-22
 * Time: 20:14
 */

namespace Deliveryman\Entity\HttpGraph;


use Deliveryman\Entity\IdentifiableInterface;
use Deliveryman\Entity\RequestConfig;

class HttpRequest implements IdentifiableInterface
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
     * List of IDs of HttpRequests that are required to be processed before the given one
     * @var array
     */
    protected $req = [];

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
     * @return HttpRequest
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
     * @return HttpRequest
     */
    public function setUri(?string $uri): self
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
     * @return HttpRequest
     */
    public function setMethod(?string $method): self
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
     * @return HttpRequest
     */
    public function setConfig(?RequestConfig $config): self
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
     * @return HttpRequest
     */
    public function setHeaders($headers): self
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
     * @return HttpRequest
     */
    public function setQuery(?array $query): self
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
     * @return HttpRequest
     */
    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getReq(): array
    {
        return $this->req;
    }

    /**
     * @param array $req
     * @return HttpRequest
     */
    public function setReq(array $req): HttpRequest
    {
        $this->req = $req;

        return $this;
    }
}