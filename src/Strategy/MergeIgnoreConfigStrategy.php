<?php
/**
 * Date: 2018-12-26
 * Time: 16:18
 */

namespace Deliveryman\Strategy;

/**
 * Class MergeIgnoreConfigStrategy
 * @package Deliveryman\Strategy
 */
class MergeIgnoreConfigStrategy extends AbstractMergeConfigStrategy
{
    const NAME = 'ignore';

    public function merge(...$configs)
    {
        return $this->fallbackConfig; // use always defaults
    }

    public function getName(): string
    {
        return self::NAME;
    }

}