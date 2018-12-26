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

    /**
     * @inheritdoc
     */
    public function merge(...$configs): array
    {
        return $this->defaults; // use always defaults
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

}