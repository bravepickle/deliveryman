<?php
/**
 * Date: 2018-12-16
 * Time: 19:29
 */

namespace Deliveryman\Exception;


use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\Request;

class ChannelException extends BaseException
{
    const MSG_QUEUE_TERMINATED = 'Queue terminated due to request errors.';
    const MSG_DEFAULT = 'Channel failed to send request.';

    /**
     * @var string
     */
    protected $message = self::MSG_DEFAULT;

    /**
     * @var Request|HttpRequest|null
     */
    protected $request;

    /**
     * @return Request|HttpRequest|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Request|HttpRequest null $request
     * @return ChannelException
     */
    public function setRequest($request): ChannelException
    {
        $this->request = $request;
        return $this;
    }

}