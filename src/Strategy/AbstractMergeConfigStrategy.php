<?php
/**
 * Date: 2018-12-26
 * Time: 15:42
 */

namespace Deliveryman\Strategy;

/**
 * Class AbstractMergeConfigStrategy
 * Merge Config
 * @package Deliveryman\Strategy
 */
abstract class AbstractMergeConfigStrategy
{
    /**
     * @var array
     */
    protected $defaults = [];

    /**
     * AbstractMergeStrategy constructor.
     * @param array $fallbackConfig
     */
    public function __construct($fallbackConfig = [])
    {
        $this->defaults = $fallbackConfig;
    }

    /**
     * @return array
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @param array $defaults
     */
    public function setDefaults(array $defaults): void
    {
        $this->defaults = $defaults;
    }

    /**
     * @param mixed ...$configs
     * @return mixed
     */
    abstract public function merge(...$configs): array;

    /**
     * Name of strategy
     * @return string
     */
    abstract public function getName(): string;

}