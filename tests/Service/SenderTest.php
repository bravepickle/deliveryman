<?php

namespace DeliverymanTest\Service;


use PHPUnit\Framework\TestCase;

class SenderTest extends TestCase
{
    /**
     * Sending batch requests
     * @dataProvider sendProvider
     */
    public function testSend($input, $expect)
    {
//        var_dump(func_get_args());
        var_dump($input);
        var_dump($expect);
        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
    }

    /**
     *
     */
    public function sendProvider()
    {
        return [
            [
                'input' => [
                    'data',
                ],
                'expect' => [
                    'foo',
                ],
            ],
        ];
    }
}