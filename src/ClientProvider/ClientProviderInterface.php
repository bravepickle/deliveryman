<?php
/**
 * Date: 14.12.18
 * Time: 14:07
 */

namespace Deliveryman\ClientProvider;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface ClientProviderInterface
 * Defines actions required to handle requests
 * @package Deliveryman\ClientProvider
 */
interface ClientProviderInterface
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
     * Send all queues.
     * Should be optimized for the best performance gains
     * @param array|RequestInterface[] $queues list of requests or associated map
     * @return ResponseInterface[]|array|null list of responses with keys as they were in request
     */
    public function sendQueues(array $queues);

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