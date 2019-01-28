<?php
/**
 * Date: 2019-01-01
 * Time: 17:17
 */

namespace Deliveryman\Service;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\BatchResponse;

interface BatchRequestHandlerInterface
{
    /**
     * Process batch request queries
     * @param BatchRequest $batchRequest
     * @return BatchResponse
     */
    public function __invoke(BatchRequest $batchRequest): BatchResponse;
}