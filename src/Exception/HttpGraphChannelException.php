<?php
/**
 * Date: 2018-12-16
 * Time: 19:29
 */

namespace Deliveryman\Exception;


use Deliveryman\Entity\HttpGraph\HttpRequest;

class HttpGraphChannelException extends ChannelException
{
    const MSG_QUEUE_TERMINATED = 'Queue terminated due to request errors.';

    /**
     * @var HttpRequest|null
     */
    protected $request;

    /**
     * @return HttpRequest|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param HttpRequest null $request
     * @return HttpGraphChannelException
     */
    public function setRequest($request): self
    {
        $this->request = $request;

        return $this;
    }

}