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

    public function merge(...$configs)
    {
        $configs = array_reverse($configs);
        $configs[] = $this->fallbackConfig; // add defaults

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

    public function getName(): string
    {
        return self::NAME;
    }

}