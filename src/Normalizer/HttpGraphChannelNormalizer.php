<?php

namespace Deliveryman\Normalizer;

use Deliveryman\Channel\HttpGraphChannel;
use Deliveryman\Entity\HttpGraph\ChannelConfig;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Exception\SerializationException;
use http\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class HttpGraphChannelNormalizer
 * @package Deliveryman\Normalizer
 */
class HttpGraphChannelNormalizer implements ChannelNormalizerInterface
{
    /**
     * Context for channel type check
     */
    const CONTEXT_CHANNEL = 'channel';

    /**
     * @var DenormalizerInterface
     */
    protected $denormalizer;

    /**
     * HttpGraphChannelNormalizer constructor.
     * @param DenormalizerInterface $denormalizer
     */
    public function __construct(DenormalizerInterface $denormalizer)
    {
        $this->denormalizer = $denormalizer;
    }

    /**
     * @inheritdoc
     */
    public function supports($data, array $context = []): bool
    {
        return isset($context[self::CONTEXT_CHANNEL]) && $context[self::CONTEXT_CHANNEL] === HttpGraphChannel::NAME;
    }

    /**
     * @inheritdoc
     */
    public function denormalizeData($data, array $context = [])
    {
        if (!$data) {
            return null;
        }

        if (!is_array($data)) {
            throw new SerializationException('Unexpected data format');
        }

        $type = HttpRequest::class;
        $output = [];
        foreach ($data as $datum) {
            if (!$this->denormalizer->supportsDenormalization($datum, $type)) {
                throw new SerializationException('Unexpected input type.');
            }

            $output[] = $this->denormalizer->denormalize($datum, $type);
        }

        return $output;
    }

    /**
     * @inheritdoc
     */
    public function denormalizeConfig($data, array $context = [])
    {
        if (!isset($data)) {
            return null;
        }

        $type = ChannelConfig::class;
        if (!$this->denormalizer->supportsDenormalization($data, $type)) {
            throw new SerializationException('Unexpected input type.');
        }

        return $this->denormalizer->denormalize($data, $type);
    }

}