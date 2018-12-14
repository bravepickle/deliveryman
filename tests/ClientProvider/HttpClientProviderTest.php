<?php
/**
 * Date: 2018-12-14
 * Time: 22:15
 */

namespace DeliverymanTest\ClientProvider;


use Deliveryman\ClientProvider\HttpClientProvider;
use Deliveryman\Entity\Request;
use Deliveryman\Service\ConfigManager;
use PHPUnit\Framework\TestCase;

class HttpClientProviderTest extends TestCase
{
    /**
     * @dataProvider basicProvider
     */
    public function testBasic(array $input)
    {
        $configManager = new ConfigManager();
        $configManager->addConfiguration(['domains' => ['example.com', 'localhost']]);

        // TODO: mock method sendRequest

        $clientProvider = new HttpClientProvider($configManager);

        $actual = $clientProvider->send($input);

        print_r($actual);
        die("\n" . __METHOD__ . ":" . __FILE__ . ":" . __LINE__ . "\n");
    }

    /**
     * @return array
     */
    public function basicProvider()
    {
        $queues = [
            [(new Request())
                ->setMethod('GET')
                ->setUri('http://example.com/comments')
//                ->setUri('http://localhost:8181/api/quizzes')
            ]
        ];

        return [
            [$queues]
        ];
    }
}