<?php

namespace Deliveryman\Entity;


use Deliveryman\Normalizer\NormalizableInterface;

class RequestConfig implements NormalizableInterface
{
    /**
     * List of requests to send together with request
     * @var RequestHeader[]|null|array
     */
    protected $headers;

    /**
     * Configs merge strategy per request
     * @var string|null
     */
    protected $configMerge;

    /**
     * Strategy on handling failed requests
     * @var string|null
     */
    protected $onFail;

    /**
     * @var array|null
     */
    protected $expectedStatusCodes;

    /**
     * @var boolean|null
     */
    protected $silent;

    /**
     * @return RequestHeader[]|null|array
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    /**
     * @param RequestHeader[]|null|array $headers
     * @return RequestConfig
     */
    public function setHeaders(?array $headers): RequestConfig
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getConfigMerge(): ?string
    {
        return $this->configMerge;
    }

    /**
     * @param string|null $configMerge
     * @return RequestConfig
     */
    public function setConfigMerge(?string $configMerge): RequestConfig
    {
        $this->configMerge = $configMerge;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOnFail(): ?string
    {
        return $this->onFail;
    }

    /**
     * @param string|null $onFail
     * @return RequestConfig
     */
    public function setOnFail(?string $onFail): RequestConfig
    {
        $this->onFail = $onFail;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getExpectedStatusCodes(): ?array
    {
        return $this->expectedStatusCodes;
    }

    /**
     * @param array|null $expectedStatusCodes
     * @return RequestConfig
     */
    public function setExpectedStatusCodes(?array $expectedStatusCodes): RequestConfig
    {
        $this->expectedStatusCodes = $expectedStatusCodes;

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getSilent(): ?bool
    {
        return $this->silent;
    }

    /**
     * @param bool|null $silent
     * @return RequestConfig
     */
    public function setSilent(?bool $silent): RequestConfig
    {
        $this->silent = $silent === null ? $silent : (bool)$silent;

        return $this;
    }


}