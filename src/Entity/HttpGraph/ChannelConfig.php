<?php
/**
 * Date: 2018-12-21
 * Time: 23:28
 */

namespace Deliveryman\Entity\HttpGraph;

use Deliveryman\Entity\ArrayConvertableInterface;

/**
 * Class ChannelConfig
 * @package Deliveryman\Entity\HttpGraph
 */
class ChannelConfig implements ArrayConvertableInterface
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

    public function toArray(): array
    {
        return [
            'expectedStatusCodes' => $this->expectedStatusCodes,
        ];
    }

    public function load(array $data, $context = []): void
    {
        $this->setExpectedStatusCodes($data['expectedStatusCodes'] ?? null);
    }

}