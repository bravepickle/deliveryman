<?php
/**
 * Date: 14.12.18
 * Time: 14:07
 */

namespace Deliveryman\Channel;

use Deliveryman\Exception\ChannelException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface ChannelInterface
 * Defines actions required to handle requests
 * @package Deliveryman\Channel
 */
interface ChannelInterface
{
    /**
     * Get client provider's name that is used within configuration. Must be unique
     * @return mixed
     */
    public function getName(): string;

    /**
     * Send all queues.
     * Should be optimized for the best performance gains
     * @param array|RequestInterface[] $queues list of requests or associated map
     * @return ResponseInterface[]|array|null list of responses with keys as they were in request
     * @throws ChannelException thrown when request send failed unexpectedly and queues must be terminated
     */
    public function send(array $queues);

    /**
     * Return all errors that appeared during last session of sending data
     * with keys taken from request data
     * @return array
     */
    public function getErrors(): array;

    /**
     * Return true if has errors
     * @return bool
     */
    public function hasErrors(): bool;

    /**
     * Remove all errors from list
     */
    public function clearErrors(): void;

    /**
     * Return all responses that considered as succeeded
     * with keys taken from request data
     * @return array|ResponseInterface[]
     */
    public function getOkResponses(): array;

    /**
     * Return true if has errors
     * @return bool
     */
    public function hasOkResponses(): bool;

    /**
     * Remove all responses from list
     */
    public function clearOkResponses(): void;

    /**
     * Return all errors that appeared during last session of sending data
     * with keys taken from request data
     * @return array|ResponseInterface[]
     */
    public function getFailedResponses(): array;

    /**
     * Return true if has errors
     * @return bool
     */
    public function hasFailedResponses(): bool;

    /**
     * Remove all errors from list
     */
    public function clearFailedResponses(): void;

    /**
     * Clear all generated data: responses, errors
     */
    public function clear(): void;

}