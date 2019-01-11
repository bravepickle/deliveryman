<?php

namespace DeliverymanTest\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Normalizer\BatchRequestNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

class BatchRequestNormalizerTest extends TestCase
{
    /**
     * @return Serializer
     */
    protected function buildSerializer()
    {
        $getSetNormalizer = new GetSetMethodNormalizer();
        $batchNormalizer = new BatchRequestNormalizer();

        $serializer = new Serializer([$batchNormalizer, $getSetNormalizer], [new JsonEncoder()]);

        return $serializer;
    }

    /**
     * Test if normalizer can parse data from array
     * @dataProvider basicProvider
     * @param array $input
     * @param array $expected
     */
    public function testBasic(array $input, array $expected)
    {
        $serializer = $this->buildSerializer();
        $this->assertTrue($serializer->supportsDenormalization($input, BatchRequest::class, null, ['channel' => 'http_graph']));

        $object = $serializer->denormalize($input, BatchRequest::class, null, ['channel' => 'http_graph']);
        $this->assertEquals(BatchRequest::class, get_class($object), 'Return object class name must match expected one');

        $actual = $serializer->normalize($object, null, ['channel' => 'http_graph']);

        $this->assertEquals($expected, $actual, 'Data denormalization differs from expected');
    }

    /**
     * Provide data for testBasic
     */
    public function basicProvider()
    {
        return [
            [
                'input' => [
                    'config' => [
                        'silent' => true,
                    ],
                ],
                'expected' => [
                    'config' => [
                        'silent' => true,
                        'configMerge' => null,
                        'onFail' => null,
                        'format' => null,
                        'channel' => null,
                    ],
                    'data' => null,
                ],
            ],
            [
                'input' => [
                    'config' => [
                        'silent' => false,
                        'configMerge' => 'first',
                        'onFail' => 'proceed',
                        'channel' => ['expectedStatusCodes' => [200],],
                    ],
                ],
                'expected' => [
                    'config' => [
                        'silent' => false,
                        'configMerge' => 'first',
                        'onFail' => 'proceed',
                        'format' => null,
                        'channel' => ['expectedStatusCodes' => [200],],
                    ],
                    'data' => null,
                ],
            ],
            [
                'input' => [
                    'data' => [
                        [
                            'id' => 'read_book',
                            'uri' => 'http://example.com/books/1',
                            'method' => 'GET',
                            'config' => [
                                'onFail' => 'abort',
                                'configMerge' => 'unique',
                                'channel' => ['expectedStatusCodes' => [200, 404],],
                            ],
                            'headers' => [
                                ['name' => 'Origin', 'value' => 'http://admin-panel.example.com'],
                            ],
                            'query' => [
                                'fields' => ['id', 'title'],
                                'include' => 'author',
                            ],
                        ],
                        [
                            'id' => 'create_author',
                            'uri' => 'http://example.com/authors?fields=id',
                            'method' => 'POST',
                            'data' => [
                                'firstname' => 'John',
                                'lastname' => 'Doe',
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'config' => null,
                    'data' => [
                        [
                            'id' => 'read_book',
                            'uri' => 'http://example.com/books/1',
                            'method' => 'GET',
                            'config' => [
                                'onFail' => 'abort',
                                'configMerge' => 'unique',
                                'silent' => null,
                                'format' => null,
                                'channel' => ['expectedStatusCodes' => [200, 404],],
                            ],
                            'headers' => [
                                ['name' => 'Origin', 'value' => 'http://admin-panel.example.com'],
                            ],
                            'query' => [
                                'fields' => ['id', 'title'],
                                'include' => 'author',
                            ],
                            'data' => null,
                            'req' => [],                        ],
                        [
                            'id' => 'create_author',
                            'uri' => 'http://example.com/authors?fields=id',
                            'method' => 'POST',
                            'config' => null,
                            'headers' => null,
                            'query' => null,
                            'data' => [
                                'firstname' => 'John',
                                'lastname' => 'Doe',
                            ],
                            'req' => [],
                        ],
                    ],
                ],
            ],
        ];
    }
}