<?php

namespace Deliveryman\Normalizer;

use Deliveryman\Channel\HttpGraphChannel;
use Deliveryman\Entity\HttpGraph\ChannelConfig;
use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\HttpGraph\HttpHeader;
use Deliveryman\Entity\RequestConfig;
use Deliveryman\Exception\SerializationException;
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

            $output[] = $this->denormalizeRequest($datum, null, $context);
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

    /**
     * @param mixed $data
     * @param string $class
     * @param null $format
     * @param array $context
     * @return HttpRequest
     * @throws SerializationException
     */
    protected function denormalizeRequest($data, $format, $context)
    {
        $object = new HttpRequest();

        // TODO: use getsetmethod or metadataClassNameNormalizer to guess names
        $object->setId($data['id'] ?? null);
        $object->setUri($data['uri'] ?? null);
        $object->setMethod($data['method'] ?? null);

        $serializer = $this->denormalizer;

        /** @var RequestConfig|null $requestConfig */
        $requestConfig = isset($data['config']) ?
            $serializer->denormalize($data['config'], RequestConfig::class, $format, $context) :
            null;

        $object->setConfig($requestConfig);

        if (!empty($data['headers'])) {
            $object->setHeaders($this->denoramlizeHeaders($data['headers'], HttpHeader::class, $format, $context));
        }

        if (!empty($data['query'])) {
            if (!is_array($data['query'])) {
                throw new SerializationException('Field "query" must contain an array.');
            }

            $object->setQuery($data['query']);
        }

        if (isset($data['data'])) {
            $object->setData($data['data']);
        }

        return $object;
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
            throw new SerializationException('Field "headers" must contain an array.');
        }

        $headers = [];
        $serializer = $this->denormalizer;

        foreach ($items as $headerItem) {
            if (!is_array($headerItem)) {
                throw new SerializationException('Each item of "headers" list must contain array.');
            }

            if (!$serializer->supportsDenormalization($headerItem, $class, $format)) {
                throw new SerializationException('Cannot denormalize data for class: ' . $class . '.');
            }

            $headers[] = $serializer->denormalize($headerItem, $class, $format, $context);
        }

        return $headers;
    }
//
//    public function denormalizeExtra($data, array $context = [])
//    {
//        // TODO: Implement denormalizeExtra() method.
//    }

}