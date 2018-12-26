<?php
/**
 * Date: 2018-12-26
 * Time: 16:18
 */

namespace Deliveryman\Strategy;

/**
 * Class MergeUniqueConfigStrategy
 * @package Deliveryman\Strategy
 */
class MergeUniqueConfigStrategy extends AbstractMergeConfigStrategy
{
    const NAME = 'unique';

    public function merge(...$configs)
    {
        $configs = array_reverse($configs);
        $configs[] = $this->fallbackConfig; // add defaults

        $config = [];
        foreach ($configs as $cfg) {
            foreach ($cfg as $name => $value) {
                if (!isset($config[$name])) { // should we override this value?
                    $config[$name] = $value;
                } elseif (is_array($config[$name])) {
                    if (is_numeric(key($config[$name]))) {
                        $config[$name] = array_values(array_unique(array_merge($config[$name], (array)$value)));
                    } else {
                        $config[$name] = $config[$name] + (array)$value; // shallow assoc array merge
                    }
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