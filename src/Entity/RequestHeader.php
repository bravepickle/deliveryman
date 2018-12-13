<?php

namespace Deliveryman\Entity;

use Deliveryman\Normalizer\NormalizableInterface;

/**
 * Class RequestHeader
 * Contains headers passed within batch request body
 * @package Deliveryman\Entity
 */
class RequestHeader implements NormalizableInterface
{
    /**
     * Header name
     * @var string|null
     */
    protected $name;

    /**
     * Header value
     * @var mixed
     */
    protected $value;

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return RequestHeader
     */
    public function setName(?string $name): RequestHeader
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     * @return RequestHeader
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

}