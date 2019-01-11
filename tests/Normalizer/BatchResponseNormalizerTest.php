<?php

namespace DeliverymanTest\Normalizer;


use Deliveryman\Entity\BatchResponse;
use Deliveryman\Entity\HttpGraph\HttpHeader;
use Deliveryman\Entity\HttpResponse;
use Deliveryman\Normalizer\BatchRequestNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

class BatchResponseNormalizerTest extends TestCase
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
     * @dataProvider normProvider
     * @param $input
     * @param $expected
     */
    public function testNorm($input, $expected)
    {
        $serializer = $this->buildSerializer();
        $this->assertTrue($serializer->supportsNormalization($input, null, ['channel' => 'http_graph']));

        $actual = $serializer->normalize($input, null, ['channel' => 'http_graph']);
        $this->assertEquals($expected, $actual, 'Data normalization differs from expected');
    }

    /**
     * Provide data for testBasic
     */
    public function normProvider()
    {
        $responseOk = (new HttpResponse())
            ->setData(
                ['id' => 46, 'name' => 'John Doe']
            )
            ->setId('read_author')
            ->setHeaders([
                (new HttpHeader())
                    ->setName('Content-Type')
                    ->setValue('application/json'),
            ])
            ->setStatusCode(200);

        $responseErr = (new HttpResponse())
            ->setData(
                'The data is invalid'
            )
            ->setId('create_book')
            ->setHeaders([
                (new HttpHeader())
                    ->setName('Content-Type')
                    ->setValue('plain/text'),
            ])
            ->setStatusCode(400);

        $batchResponse = (new BatchResponse())
            ->setData([
                $responseOk->getId() => $responseOk,
            ])
            ->setStatus(BatchResponse::STATUS_FAILED)
            ->setErrors([$responseErr->getId() => $responseErr])
        ;

        return [
            [
                'input' => $batchResponse,
                'expected' => [
                    'data' => [
                        'read_author' => [
                            'id' => 'read_author',
                            'headers' => [
                                ['name' => 'Content-Type', 'value' => 'application/json'],
                            ],
                            'statusCode' => 200,
                            'data' => ['id' => 46, 'name' => 'John Doe'],
                        ],
                    ],
                    'errors' => [
                        'create_book' => [
                            'id' => 'create_book',
                            'headers' => [
                                ['name' => 'Content-Type', 'value' => 'plain/text'],
                            ],
                            'statusCode' => 400,
                            'data' => 'The data is invalid',
                        ],
                    ],
                    'status' => 'failed',
                    'failed' => null
                ],
            ],
        ];
    }
}