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
    protected $fallbackConfig = [];

    /**
     * AbstractMergeStrategy constructor.
     * @param array $fallbackConfig
     */
    public function __construct($fallbackConfig = [])
    {
        $this->fallbackConfig = $fallbackConfig;
    }

    /**
     * @param mixed ...$configs
     * @return mixed
     */
    abstract public function merge(...$configs);

    /**
     * Name of strategy
     * @return string
     */
    abstract public function getName(): string;

}