<?php

namespace Deliveryman\Entity;


use Deliveryman\Entity\HttpQueue\ChannelConfig;
use Deliveryman\Normalizer\NormalizableInterface;

class RequestConfig implements NormalizableInterface
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
     * @var mixed channel-related configuration
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
     * @return mixed|ChannelConfig
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     * @return RequestConfig
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

}