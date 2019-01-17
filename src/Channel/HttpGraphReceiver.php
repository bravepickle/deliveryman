<?php
/**
 * Date: 2019-01-17
 * Time: 22:58
 */

namespace Deliveryman\Channel;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Normalizer\HttpGraphChannelNormalizer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Serializer\SerializerInterface;

class HttpGraphReceiver implements ReceiverInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * HttpGraphReceiver constructor.
     * @param SerializerInterface $serializer
     * @param $data
     */
    public function __construct(SerializerInterface $serializer, $data)
    {
        $this->serializer = $serializer;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function receive(callable $handler): void
    {
        $batchRequest = $this->serializer->deserialize(
            $this->data,
            BatchRequest::class,
            null,
            [HttpGraphChannelNormalizer::CONTEXT_CHANNEL => HttpGraphChannel::NAME,]
        );

        $handler(new Envelope($batchRequest));
    }

    /**
     * @inheritdoc
     */
    public function stop(): void
    {
        // noop. This feature is not supported.
    }

}