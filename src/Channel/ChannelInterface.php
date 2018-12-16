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
//    /**
//     * Send single request and receive response
//     * @param RequestInterface $request
//     * @param RequestMetaDataInterface|null $metaData
//     * @return mixed
//     */
//    public function send(RequestInterface $request, ?RequestMetaDataInterface $metaData);
//
//    /**
//     * Send many requests in sequence
//     * @param array|RequestInterface[] $requests list of requests or associated map
//     * @return ResponseInterface[]|array|null list of responses with keys as they were in request
//     */
//    public function sendQueue(array $requests);

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

}