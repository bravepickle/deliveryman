<?php

namespace Deliveryman\Entity;


use Deliveryman\Normalizer\NormalizableInterface;

class HttpChannelConfig implements NormalizableInterface
{
    /**
     * @var array|null
     */
    protected $headers;

    /**
     * @var array|null
     */
    protected $expectedStatusCodes;
}