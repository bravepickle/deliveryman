<?php

namespace Deliveryman\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Exception\SerializationException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class BatchRequestNormalizer implements SerializerAwareInterface, DenormalizerInterface
{
    use ObjectToPopulateTrait;
    use SerializerAwareTrait;

    /**
     * Ignore given entity if this field is set in context
     */
    const CONTEXT_IGNORE = 'deliveryman_ignore';

    /**
     * @var ChannelNormalizerInterface[]
     */
    protected $channelNormalizers = [];

    /**
     * BatchRequestNormalizer constructor.
     * @param ChannelNormalizerInterface[] $channelNormalizers
     */
    public function __construct(array $channelNormalizers = [])
    {
        $this->channelNormalizers = $channelNormalizers;
    }

    /**
     * Add default normalizers
     */
    protected function addDefaultChannelNormalizers()
    {
        $this->addChannelNormalizer(new HttpGraphChannelNormalizer($this->getSerializer()));
    }

    /**
     * @param ChannelNormalizerInterface $normalizer
     * @return $this
     */
    public function addChannelNormalizer(ChannelNormalizerInterface $normalizer)
    {
        $this->channelNormalizers[] = $normalizer;

        return $this;
    }

    /**
     * @return \Symfony\Component\Serializer\SerializerInterface|DenormalizerInterface
     */
    protected function getSerializer()
    {
        if (!$this->serializer) {
            throw new InvalidArgumentException('Serializer is not set.');
        }

        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new InvalidArgumentException('Expected a serializer that also implements DenormalizerInterface.');
        }

        return $this->serializer;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|NormalizableInterface
     * @throws SerializationException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        switch (true) {
            case $this->inheritsClass($class, BatchRequest::class):
                return $this->denormalizeBatchRequest($data, $class, $format, $context);
            case $this->inheritsClass($class, RequestConfig::class):
                return $this->denormalizeRequestConfig($data, $class, $context);
            default:
                throw new LogicException('Cannot denormalize data for unexpected class: ' . $class . '.');
        }
    }

    /**
     * Check if given class
     *
     * @param $class
     * @param $targetClass
     * @return bool
     */
    protected function inheritsClass($class, $targetClass)
    {
        return $class === $targetClass || \is_subclass_of($class, $targetClass);
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $data && \is_subclass_of($type, NormalizableInterface::class);
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param array $context
     * @return object|BatchRequest
     * @throws SerializationException
     */
    protected function denormalizeRequestConfig($data, $class, $context)
    {
        /** @var RequestConfig $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();

        $object->setFormat($data['format'] ?? null);
        $object->setConfigMerge($data['configMerge'] ?? null);
        $object->setOnFail($data['onFail'] ?? null);
        $object->setSilent($data['silent'] ?? null);

        if (isset($data['channel'])) {
            $object->setChannel(
                $this->detectChannelNormalizer($data, $context)
                    ->denormalizeConfig($data['channel'], $context)
            );
        }

        return $object;
    }

    /**
     * @param $data
     * @param array $context
     * @param bool $throwException
     * @return ChannelNormalizerInterface
     * @throws SerializationException
     */
    protected function detectChannelNormalizer($data, array $context, $throwException = true): ChannelNormalizerInterface
    {
        if (!$this->channelNormalizers) {
            $this->addDefaultChannelNormalizers();
        }

        foreach ($this->channelNormalizers as $normalizer) {
            if ($normalizer->supports($data, $context)) {
                return $normalizer;
            }
        }

        if ($throwException) {
            throw new SerializationException('Cannot detect channel type that supports given data.');
        }

        return null;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|BatchRequest
     * @throws SerializationException
     */
    protected function denormalizeBatchRequest($data, $class, $format, $context)
    {
        /** @var BatchRequest $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();
        $serializer = $this->getSerializer();

        /** @var RequestConfig|null $requestConfig */
        $requestConfig = isset($data['config']) ?
            $serializer->denormalize($data['config'], RequestConfig::class, $format, $context) :
            null;

        $object->setConfig($requestConfig);

        if (!empty($data['data'])) {
            $object->setData(
                $this->detectChannelNormalizer($data, $context)
                    ->denormalizeData($data['data'], $context)
            );
        }

        return $object;
    }
}