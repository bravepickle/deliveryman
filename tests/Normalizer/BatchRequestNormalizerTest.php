<?php

namespace DeliverymanTest\Normalizer;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Normalizer\BatchRequestNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
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

        return $serializer;
    }

    /**
     * Test if normalizer can parse data from array
     * @dataProvider basicProvider
     * @param array $data
     */
    public function testBasic(array $data)
    {
        $serializer = $this->buildSerializer();
        $this->assertTrue($serializer->supportsDenormalization($data['input'], BatchRequest::class));

        $object = $serializer->denormalize($data['input'], BatchRequest::class);
        $this->assertEquals(BatchRequest::class, get_class($object), 'Return object class name must match expected one');

        $actual = $serializer->normalize($object);
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
                        ],
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
            [
                [
                    'input' => [
                        'config' => [
                            'silent' => false,
                            'configMerge' => 'first',
                            'onFail' => 'proceed',
                            'expectedStatusCodes' => 200,
                            'headers' => [
                                ['name' => 'Content-Type', 'value' => 'application/json'],
                            ],
                        ],
                    ],
                    'expected' => [
                        'config' => [
                            'silent' => false,
                            'headers' => [
                                ['name' => 'Content-Type', 'value' => 'application/json'],
                            ],
                            'configMerge' => 'first',
                            'onFail' => 'proceed',
                            'expectedStatusCodes' => [200],
                        ],
                        'queues' => null,
                    ],
                ],
            ],
            [
                [
                    'input' => [
                        'queues' => [
                            [ // queue #1
                                [ // request #1
                                    'id' => 'read_book',
                                    'uri' => 'http://example.com/books/1',
                                    'method' => 'GET',
                                    'config' => [
                                        'onFail' => 'abort',
                                        'headers' => [
                                            ['name' => 'Accept-Language', 'value' => 'en_US'],
                                        ],
                                        'configMerge' => 'unique',
                                        'expectedStatusCodes' => [200, 404],
                                    ],
                                    'headers' => [
                                        ['name' => 'Origin', 'value' => 'http://admin-panel.example.com'],
                                    ],
                                    'query' => [
                                        'fields' => ['id', 'title'],
                                        'include' => 'author',
                                    ],
                                ],
                            ],
                            [ // queue #2 with single POST request
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
                        'queues' => [
                            [
                                [
                                    'id' => 'read_book',
                                    'uri' => 'http://example.com/books/1',
                                    'method' => 'GET',
                                    'config' => [
                                        'onFail' => 'abort',
                                        'headers' => [
                                            ['name' => 'Accept-Language', 'value' => 'en_US'],
                                        ],
                                        'configMerge' => 'unique',
                                        'expectedStatusCodes' => [200, 404],
                                        'silent' => null,
                                    ],
                                    'headers' => [
                                        ['name' => 'Origin', 'value' => 'http://admin-panel.example.com'],
                                    ],
                                    'query' => [
                                        'fields' => ['id', 'title'],
                                        'include' => 'author',
                                    ],
                                    'data' => null,
                                ],
                            ],
                            [
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
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}