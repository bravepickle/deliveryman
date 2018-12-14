<?php

namespace Deliveryman\EventListener;

use Deliveryman\Entity\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\Event as BasicEvent;

class BuildResponseEvent extends BasicEvent
{
    /**
     * Is called after return response was built from another
     */
    const EVENT_POST_BUILD = 'deliveryman.response.post_build';

    /**
     * @var Response|null
     */
    protected $targetResponse;

    /**
     * @var ResponseInterface|null
     */
    protected $sourceResponse;

    /**
     * BuildResponseEvent constructor.
     * @param Response|null $targetResponse
     * @param ResponseInterface $sourceResponse
     */
    public function __construct(?Response $targetResponse, ?ResponseInterface $sourceResponse)
    {
        $this->targetResponse = $targetResponse;
        $this->sourceResponse = $sourceResponse;
    }

    /**
     * @return Response|null
     */
    public function getTargetResponse(): ?Response
    {
        return $this->targetResponse;
    }

    /**
     * @param Response|null $targetResponse
     * @return BuildResponseEvent
     */
    public function setTargetResponse(?Response $targetResponse): BuildResponseEvent
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

}