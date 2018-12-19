<?php
declare(strict_types=1);

/**
 * Date: 2018-12-13
 * Time: 00:05
 */

namespace Deliveryman\Service;

use Deliveryman\DependencyInjection\Configuration;
use Deliveryman\EventListener\Event;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ConfigBuilder
 * Generate configuration based in input data and defaults
 * @package Deliveryman\Service
 */
class ConfigManager
{
    const CACHE_KEY_CONFIG = 'deliveryman.config';
    /**
     * List of additional configs
     * @var array
     */
    protected $configs;

    /**
     * @var CacheItemPoolInterface|null
     */
    protected $cache;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * ConfigBuilder constructor.
     * @param array $configs
     * @param EventDispatcherInterface|null $dispatcher
     * @param CacheItemPoolInterface|null $cacheItemPool
     */
    public function __construct(
        array $configs = [],
        ?EventDispatcherInterface $dispatcher = null,
        ?CacheItemPoolInterface $cacheItemPool = null
    ) {
        $this->configs = $configs;
        $this->cache = $cacheItemPool;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Build all configs together with master config
     * @param array ...$configs
     * @return array
     */
    public function build(...$configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    /**
     * Append config to list of existing ones
     * @param array $config
     * @return ConfigManager
     */
    public function addConfiguration(array $config): self
    {
        $this->configs[] = $config;

        return $this;
    }

    /**
     * Build configuration from all available configs
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getConfiguration()
    {
        if ($this->cache) {
            $cacheItem = $this->cache->getItem(self::CACHE_KEY_CONFIG);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $config = $this->build(...$this->configs);
            $cacheItem->set($config);

            if ($this->dispatcher) {
                $this->dispatcher->dispatch(Event::EVENT_PRE_SAVE, new Event($cacheItem));
            }

            $this->cache->save($cacheItem);

            return $config;
        }

        return $this->build(...$this->configs);
    }
}