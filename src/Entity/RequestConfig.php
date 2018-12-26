<?php

namespace Deliveryman\Entity;


use Deliveryman\Entity\HttpQueue\ChannelConfig;
use Deliveryman\Exception\InvalidArgumentException;
use Deliveryman\Normalizer\NormalizableInterface;

class RequestConfig implements NormalizableInterface, ArrayConvertableInterface
{
    const CFG_MERGE_FIRST = 'first';
    const CFG_MERGE_UNIQUE = 'unique';
    const CFG_MERGE_IGNORE = 'ignore';

    const CFG_ON_FAIL_ABORT = 'abort';
    const CFG_ON_FAIL_PROCEED = 'proceed';
    const CFG_ON_FAIL_ABORT_QUEUE = 'abort-queue';

    /**
     * Configs merge strategy per request
     * @var string|null
     */
    protected $configMerge;

    /**
     * Strategy on handling failed requests
     * @var string|null
     */
    protected $onFail;

    /**
     * @var boolean|null
     */
    protected $silent;

    /**
     * @var string|null Expected output format for responses
     */
    protected $format;

    /**
     * @var ArrayConvertableInterface|null channel-related configuration
     */
    protected $channel;

    /**
     * @return string|null
     */
    public function getConfigMerge(): ?string
    {
        return $this->configMerge;
    }

    /**
     * @param string|null $configMerge
     * @return RequestConfig
     */
    public function setConfigMerge(?string $configMerge): RequestConfig
    {
        $this->configMerge = $configMerge;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOnFail(): ?string
    {
        return $this->onFail;
    }

    /**
     * @param string|null $onFail
     * @return RequestConfig
     */
    public function setOnFail(?string $onFail): RequestConfig
    {
        $this->onFail = $onFail;

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getSilent(): ?bool
    {
        return $this->silent;
    }

    /**
     * @param bool|null $silent
     * @return RequestConfig
     */
    public function setSilent(?bool $silent): RequestConfig
    {
        $this->silent = $silent === null ? $silent : (bool)$silent;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * @param string|null $format
     * @return RequestConfig
     */
    public function setFormat(?string $format): RequestConfig
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @return null|ChannelConfig|ArrayConvertableInterface
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     * @return RequestConfig
     */
    public function setChannel(?ArrayConvertableInterface $channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        return [
            'configMerge' => $this->configMerge,
            'onFail' => $this->onFail,
            'silent' => $this->silent,
            'format' => $this->format,
            'channel' => $this->channel === null ? null : $this->channel->toArray(),
        ];
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function load(array $data, $context = []): void
    {
        $this->setConfigMerge($data['configMerge'] ?? null);
        $this->setOnFail($data['onFail'] ?? null);
        $this->setSilent($data['silent'] ?? null);
        $this->setFormat($data['format'] ?? null);

        if (!isset($context['channel_class'])) {
            throw new InvalidArgumentException('Channel class for config was not set.');
        }

        $channel = new $context['channel_class']();
        if (isset($data['channel'])) {
            /** @var ArrayConvertableInterface $channel */
            $channel->load($data['channel'], $context);
        }

        $this->setChannel($channel);
    }


}