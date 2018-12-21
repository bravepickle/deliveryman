<?php
/**
 * Date: 2018-12-21
 * Time: 23:28
 */

namespace Deliveryman\Entity\HttpQueue;

/**
 * Class ChannelConfig
 * @package Deliveryman\Entity\HttpQueue
 */
class ChannelConfig
{
    /**
     * @var array|null
     */
    protected $expectedStatusCodes;

    /**
     * @return array|null
     */
    public function getExpectedStatusCodes(): ?array
    {
        return $this->expectedStatusCodes;
    }

    /**
     * @param array|null $expectedStatusCodes
     * @return ChannelConfig
     */
    public function setExpectedStatusCodes(?array $expectedStatusCodes): self
    {
        $this->expectedStatusCodes = $expectedStatusCodes;

        return $this;
    }
}