<?php

namespace DeliverymanTest\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Normalizer\BatchRequestNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

class BatchRequestNormalizerTest extends TestCase
{
    /**
     * @return Serializer
     */
    protected function buildSerializer()
    {
        $classMetadataFactory = new ClassMetadataFactory(
            new YamlFileLoader(__DIR__ . '/../../src/Resources/serialization.yaml')
        );
        $getSetNormalizer = new GetSetMethodNormalizer($classMetadataFactory);
        $batchNormalizer = new BatchRequestNormalizer();

        $serializer = new Serializer([$batchNormalizer, $getSetNormalizer], [new JsonEncoder()]);

//        xdebug_var_dump($serializer);
//        die("\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");

        return $serializer;
    }

    /**
     * Test if normalizer can parse data from array
     * @dataProvider basicProvider
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testBasic($data)
    {
//        $normalizer = new CustomNormalizer(); // TODO: research its specifics and object populate logic
        $serializer = $this->buildSerializer();


        $this->assertTrue($serializer->supportsDenormalization($data['input'], BatchRequest::class));

        $object = $serializer->denormalize($data['input'], BatchRequest::class);

        $this->assertEquals(BatchRequest::class, get_class($object), 'Return object class name must match expected one');

//        $actual = $serializer->normalize($object, null, ['skip_null_values' => true]);
        $actual = $serializer->normalize($object);

//        xdebug_var_dump($actual);
//        xdebug_var_dump($data);

        $this->assertEquals($data['expected'], $actual, 'Data denormalization differs from expected');
    }

    /**
     * Provide data for testBasic
     */
    public function basicProvider()
    {
        return [
            [
                [
                    'input' => [
                        'config' => [
                            'silent' => true,
                        ]
                    ],
                    'expected' => [
                        'config' => [
                            'silent' => true,
                            'headers' => null,
                            'configMerge' => null,
                            'onFail' => null,
                            'expectedStatusCodes' => null,
                        ],
                        'queues' => null,
                    ],
                ],
            ],
        ];
    }
}