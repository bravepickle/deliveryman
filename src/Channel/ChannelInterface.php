<?php
/**
 * Date: 14.12.18
 * Time: 14:07
 */

namespace Deliveryman\Channel;

use Deliveryman\Entity\BatchRequest;
use Deliveryman\Exception\ChannelException;
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
     * @param BatchRequest $batchRequest
     * @return ResponseInterface[]|array|null list of responses with keys as they were in request
     * @throws ChannelException
     */
    public function send(BatchRequest $batchRequest);

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
     * Add error
     * @param $path
     * @param $message
     * @return $this
     */
    public function addError($path, $message);

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
     * Add succeeded response
     * @param $path
     * @param ResponseInterface $response
     * @return $this
     */
    public function addOkResponse($path, ResponseInterface $response);

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
     * Add failed response
     * @param $path
     * @param ResponseInterface $response
     * @return $this
     */
    public function addFailedResponse($path, ResponseInterface $response);

    /**
     * Clear all generated data: responses, errors
     */
    public function clear(): void;

}