<?php
declare(strict_types = 1);

/**
 * Date: 2018-12-13
 * Time: 00:05
 */

namespace Deliveryman\Service;

use Deliveryman\Config\Configuration;
use Symfony\Component\Config\Definition\Processor;

/**
 * Class ConfigBuilder
 * Generate configuration based in input data and defaults
 * @package Deliveryman\Service
 */
class ConfigBuilder
{
    public function build(...$configs): array
    {
//        \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}