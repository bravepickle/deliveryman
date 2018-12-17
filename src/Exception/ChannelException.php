<?php
/**
 * Date: 2018-12-16
 * Time: 19:29
 */

namespace Deliveryman\Exception;


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
     * @var Request|null
     */
    protected $request;

    /**
     * @return Request|null
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * @param Request|null $request
     * @return ChannelException
     */
    public function setRequest(?Request $request): ChannelException
    {
        $this->request = $request;
        return $this;
    }

}