<?php

namespace Deliveryman\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Exception\SerializationException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;
use Symfony\Component\Yaml\Yaml;

class BatchRequestNormalizer implements SerializerAwareInterface, DenormalizerInterface
{
    use ObjectToPopulateTrait;
    use SerializerAwareTrait;

    /**
     * Ignore given entity if this field is set in context
     */
    const CONTEXT_IGNORE = 'deliveryman_ignore';

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
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        switch ($class) {
            case BatchRequest::class:
                return $this->denormalizeBatchRequest($data, $class, $format, $context);
            case RequestConfig::class:
                return $this->denormalizeRequestConfig($data, $class, $format, $context);
            default:
                throw new LogicException('Cannot denormalize data for unexpected class: ' . $class . '.');
        }
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

    protected function denoramlizeHeaders($items, $class, $format, $context)
    {
        if (!is_array($items)) {
            throw new MappingException('Field "headers" must contain an array.');
        }

        $headers = [];
        $serializer = $this->getSerializer();

        foreach ($items as $headerItem) {
            if (!is_array($headerItem)) {
                throw new InvalidArgumentException('Each item of "headers" list must contain array.');
            }

            if (!$serializer->supportsDenormalization($headerItem, $class, $format)) {
                throw new SerializationException('Cannot denormalize data for class: ' . $class . '.');
            }

            $requests[] = $serializer->denormalize($headerItem, $class, $format, $context);
        }

        return $headers;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|BatchRequest
     */
    protected function denormalizeRequestConfig($data, $class, $format, $context)
    {
        /** @var RequestConfig $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();
        $serializer = $this->getSerializer();

//        /** @var RequestConfig|null $requestConfig */
//        $requestConfig = isset($data['config']) ?
//            $serializer->denormalize($data, RequestConfig::class, $format) :
//            null;
//
//        $object->setConfig($requestConfig);

        $cfgContext = $context;
        $cfgContext['ignored_attributes'] = ['headers'];
        $cfgContext['object_to_populate'] = $object;
        $cfgContext[self::CONTEXT_IGNORE] = true;

        $serializer->denormalize($data, $class, $format, $cfgContext);

        var_export($object);
        die("\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");

        if (!empty($data['headers'])) {
            $object->setHeaders($this->denoramlizeHeaders($data['queues'], $format, $context));
        }

        return $object;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|BatchRequest
     */
    protected function denormalizeBatchRequest($data, $class, $format, $context)
    {
        /** @var BatchRequest $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();
        $serializer = $this->getSerializer();

        /** @var RequestConfig|null $requestConfig */
        $requestConfig = isset($data['config']) ?
            $serializer->denormalize($data, RequestConfig::class, $format) :
            null;

        $object->setConfig($requestConfig);

        if (!empty($data['queues'])) {
            $object->setQueues($this->denormalizeQueues($data['queues'], $format));
        }

        return $object;
    }

    /**
     * @param array $items
     * @param string $format
     * @return array
     * @throws SerializationException
     */
    protected function denormalizeQueues($items, $format)
    {
        if (!is_array($items)) {
            throw new MappingException('Field "queues" must contain an array.');
        }

        $class = Request::class;
        $queues = [];
        $serializer = $this->getSerializer();

        foreach ($items as $queue) {
            if (!is_array($queue)) {
                $queue = [$queue];
            }

            $requests = [];
            foreach ($queue as $requestItem) {
                if (!$serializer->supportsDenormalization($requestItem, $class, $format)) {
                    throw new SerializationException('Cannot denormalize data for class: ' . $class . '.');
                }

                $requests[] = $serializer->denormalize($requestItem, $class, $format);
            }
        }

        return $queues;
    }

//    /**
//     * @param $class
//     * @return string
//     */
//    protected function  getCacheKeyDtoConfig($class): string
//    {
//        // Key cannot contain backslashes according to PSR-6
//        return self::CACHE_PREFIX . strtr($class, '\\', '_');
//    }
}