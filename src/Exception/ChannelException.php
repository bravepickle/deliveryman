<?php
/**
 * Date: 2018-12-16
 * Time: 19:29
 */

namespace Deliveryman\Exception;


class ChannelException extends BaseException
{
    const MSG_DEFAULT = 'Channel failed to send request.';

    /**
     * @var string
     */
    protected $message = self::MSG_DEFAULT;
}