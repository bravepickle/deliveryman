<?php
/**
 * Date: 2018-12-26
 * Time: 18:36
 */

namespace Deliveryman\Strategy;


use Deliveryman\Exception\InvalidArgumentException;

class MergeRequestConfigStrategy extends AbstractMergeConfigStrategy
{
    const NAME = 'channel.request_config';

    /**
     * @var string type of merge strategy
     */
    protected $configMerge;

    /**
     * @var AbstractMergeConfigStrategy[]
     */
    protected $mergeConfigStrategies = [];

    /**
     * @var AbstractMergeConfigStrategy[]
     */
    protected $channelMergeConfigStrategies = [];

    /**
     * MergeRequestConfigStrategy constructor.
     * @param array $fallbackConfig
     */
    public function __construct($fallbackConfig = [])
    {
        parent::__construct($fallbackConfig);
        $this->initMergeStrategies();
    }

    /**
     * @param string $configMerge
     */
    public function setConfigMerge(string $configMerge): void
    {
        $this->configMerge = $configMerge;
    }

    protected function initMergeStrategies(): void
    {
        $this->addMergeConfigStrategy(new MergeUniqueConfigStrategy($this->defaults))
            ->addMergeConfigStrategy(new MergeFirstConfigStrategy($this->defaults))
            ->addMergeConfigStrategy(new MergeIgnoreConfigStrategy($this->defaults));
    }

    /**
     * @param AbstractMergeConfigStrategy $strategy
     * @return $this
     */
    public function addMergeConfigStrategy(AbstractMergeConfigStrategy $strategy)
    {
        $this->mergeConfigStrategies[$strategy->getName()] = $strategy;

        $channelStrategy = clone $strategy;
        $channelStrategy->setDefaults($strategy->getDefaults()['channel'] ?? []);
        $this->channelMergeConfigStrategies[$strategy->getName()] = $channelStrategy;

        return $this;
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function merge(...$configs): array
    {
        if (!isset($this->mergeConfigStrategies[$this->configMerge])) {
            throw new InvalidArgumentException('Unknown merge strategy: ' . $this->configMerge . '.');
        }
        $channelConfigs = array_column($configs, 'channel'); // extract all channel configs. Merge separately
        $mergeStrategy = $this->mergeConfigStrategies[$this->configMerge];
        $channelStrategy = $this->channelMergeConfigStrategies[$this->configMerge];

        $resultingConfig = $mergeStrategy->merge($configs);
        $resultingConfig['channel'] = $channelStrategy->merge(...$channelConfigs); // merge channel configs

        return $resultingConfig;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

}