<?php

namespace DeliverymanTest\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Normalizer\BatchRequestNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class BatchRequestNormalizerTest extends TestCase
{
    /**
     * @return GetSetMethodNormalizer
     */
    protected function getGetSetNormalizer()
    {
        $normalizer = new GetSetMethodNormalizer();

        return $normalizer;
    }

    /**
     * Test if normalizer can parse data from array
     * @dataProvider basicProvider
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testBasic($data)
    {
        $normalizer = new CustomNormalizer(); // TODO: research its specifics and object populate logic
        $normalizer = new BatchRequestNormalizer($this->getGetSetNormalizer());

        $this->assertTrue($normalizer->supportsDenormalization($data['input'], BatchRequest::class));

        $actual = $normalizer->denormalize($data['input'], BatchRequest::class);

        print_r($actual);

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

                    ],
                ],
            ],
        ];
    }
}