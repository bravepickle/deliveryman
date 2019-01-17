<?php
/**
 * Date: 2018-12-26
 * Time: 16:18
 */

namespace Deliveryman\Strategy;

/**
 * Class MergeFirstConfigStrategy
 * @package Deliveryman\Strategy
 */
class MergeFirstConfigStrategy extends AbstractMergeConfigStrategy
{
    const NAME = 'first';

    /**
     * @inheritdoc
     */
    public function merge(...$configs): array
    {
        $configs = array_reverse(array_filter($configs));
        $configs[] = $this->defaults; // add defaults

        $config = [];
        foreach ($configs as $cfg) {
            foreach ($cfg as $name => $value) {
                if (!isset($config[$name])) { // should we override this value?
                    $config[$name] = $value;
                }
            }
        }

        return $config;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

}