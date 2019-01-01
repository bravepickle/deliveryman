<?php
/**
 * Date: 2019-01-01
 * Time: 17:17
 */

namespace Deliveryman\Service;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;
use Deliveryman\Exception\SendingException;

interface SenderInterface
{
    /**
     * Process batch request queries
     * @param BatchRequest $batchRequest
     * @return BatchResponse
     * @throws SendingException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function send(BatchRequest $batchRequest): BatchResponse;
}