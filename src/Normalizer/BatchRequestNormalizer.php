<?php

namespace Deliveryman\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\HttpQueue\ChannelConfig;
use Deliveryman\Entity\Request;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Entity\RequestHeader;
use Deliveryman\Exception\SerializationException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MappingException;
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
        switch ($class) {
            case BatchRequest::class:
                return $this->denormalizeBatchRequest($data, $class, $format, $context);
            case Request::class:
                return $this->denormalizeRequest($data, $class, $format, $context);
            case RequestConfig::class:
                return $this->denormalizeRequestConfig($data, $class, $format, $context);
            case RequestHeader::class:
                return $this->denormalizeRequestHeader($data, $class, $context);
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

    /**
     * @param array $items
     * @param string $class
     * @param string $format
     * @param array $context
     * @return array
     * @throws SerializationException
     */
    protected function denoramlizeHeaders($items, $class, $format, $context)
    {
        if (!is_array($items)) {
            throw new InvalidArgumentException('Field "headers" must contain an array.');
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

            $headers[] = $serializer->denormalize($headerItem, $class, $format, $context);
        }

        return $headers;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|BatchRequest
     * @throws SerializationException
     */
    protected function denormalizeRequestConfig($data, $class, $format, $context)
    {
        /** @var RequestConfig $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();

        $object->setFormat($data['format'] ?? null);
        $object->setConfigMerge($data['configMerge'] ?? null);
        $object->setOnFail($data['onFail'] ?? null);
        $object->setSilent($data['silent'] ?? null);

        if (isset($data['channel']) && $this->serializer instanceof DenormalizerInterface) {
            $object->setChannel($this->serializer->denormalize($data['channel'], ChannelConfig::class, $format));
        }

        return $object;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return object|BatchRequest
     * @throws SerializationException
     */
    protected function denormalizeRequest($data, $class, $format, $context)
    {
        /** @var Request $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();

        // TODO: use getsetmethod or metadataClassNameNormalizer to guess names
        $object->setId($data['id'] ?? null);
        $object->setUri($data['uri'] ?? null);
        $object->setMethod($data['method'] ?? null);

        $serializer = $this->getSerializer();

        /** @var RequestConfig|null $requestConfig */
        $requestConfig = isset($data['config']) ?
            $serializer->denormalize($data['config'], RequestConfig::class, $format) :
            null;

        $object->setConfig($requestConfig);

        if (!empty($data['headers'])) {
            $object->setHeaders($this->denoramlizeHeaders($data['headers'], RequestHeader::class, $format, $context));
        }

        if (!empty($data['query'])) {
            if (!is_array($data['query'])) {
                throw new InvalidArgumentException('Field "query" must contain an array.');
            }

            $object->setQuery($data['query']);
        }

        $object->setData($data['data'] ?? null);

        return $object;
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param array $context
     * @return object|BatchRequest
     */
    protected function denormalizeRequestHeader($data, $class, $context)
    {
        /** @var RequestHeader $object */
        $object = $this->extractObjectToPopulate($class, $context) ?: new $class();

        $object->setValue($data['value'] ?? null);
        $object->setName($data['name'] ?? null);

        return $object;
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
            $serializer->denormalize($data['config'], RequestConfig::class, $format) :
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
                throw new InvalidArgumentException('Field "queues" must contain an array of requests.');
            } elseif (array_key_exists('uri', $queue)) {
                $queue = [$queue]; // wrap single request with array
            }

            $requests = [];
            foreach ($queue as $requestItem) {
                if (!$serializer->supportsDenormalization($requestItem, $class, $format)) {
                    throw new SerializationException('Cannot denormalize data for class: ' . $class . '.');
                }

                $requests[] = $serializer->denormalize($requestItem, $class, $format);
            }

            $queues[] = $requests;
        }

        return $queues;
    }
}