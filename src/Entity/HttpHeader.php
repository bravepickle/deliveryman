<?php

namespace Deliveryman\Entity;

use Deliveryman\Normalizer\NormalizableInterface;

/**
 * Class RequestHeader
 * Contains headers passed within batch request body
 * @package Deliveryman\Entity
 */
class HttpHeader implements NormalizableInterface
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
     * RequestHeader constructor.
     * @param string|null $name
     * @param mixed $value
     */
    public function __construct($name = null, $value = null)
    {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return HttpHeader
     */
    public function setName(?string $name): HttpHeader
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
     * @return HttpHeader
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

}