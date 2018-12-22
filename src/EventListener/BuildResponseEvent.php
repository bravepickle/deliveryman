<?php

namespace Deliveryman\EventListener;

use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\HttpQueue\ResponseData;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\Event as BasicEvent;

class BuildResponseEvent extends BasicEvent
{
    /**
     * Is called after return response was built from another
     */
    const EVENT_POST_BUILD = 'deliveryman.response.post_build';

    /**
     * Is called after return failed response was built from another
     */
    const EVENT_FAILED_POST_BUILD = 'deliveryman.response.failed_post_build';

    /**
     * @var ResponseData|null
     */
    protected $targetResponse;

    /**
     * @var ResponseInterface|null
     */
    protected $sourceResponse;

    /**
     * @var RequestConfig|null
     */
    protected $requestConfig;

    /**
     * BuildResponseEvent constructor.
     * @param ResponseData|null $targetResponse
     * @param ResponseInterface $sourceResponse
     * @param RequestConfig|null $requestConfig
     */
    public function __construct(?ResponseData $targetResponse, ?ResponseInterface $sourceResponse, ?RequestConfig $requestConfig)
    {
        $this->targetResponse = $targetResponse;
        $this->sourceResponse = $sourceResponse;
        $this->requestConfig = $requestConfig;
    }

    /**
     * @return ResponseData|null
     */
    public function getTargetResponse(): ?ResponseData
    {
        return $this->targetResponse;
    }

    /**
     * @param ResponseData|null $targetResponse
     * @return BuildResponseEvent
     */
    public function setTargetResponse(?ResponseData $targetResponse): BuildResponseEvent
    {
        $this->targetResponse = $targetResponse;

        return $this;
    }

    /**
     * @return ResponseInterface|null
     */
    public function getSourceResponse(): ?ResponseInterface
    {
        return $this->sourceResponse;
    }

    /**
     * @param ResponseInterface|null $sourceResponse
     * @return BuildResponseEvent
     */
    public function setSourceResponse(?ResponseInterface $sourceResponse): BuildResponseEvent
    {
        $this->sourceResponse = $sourceResponse;

        return $this;
    }

    /**
     * @return RequestConfig|null
     */
    public function getRequestConfig(): ?RequestConfig
    {
        return $this->requestConfig;
    }

    /**
     * @param RequestConfig|null $requestConfig
     * @return BuildResponseEvent
     */
    public function setRequestConfig(?RequestConfig $requestConfig): BuildResponseEvent
    {
        $this->requestConfig = $requestConfig;
        return $this;
    }

}