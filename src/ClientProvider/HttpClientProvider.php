<?php

namespace Deliveryman\ClientProvider;

use Deliveryman\Service\ConfigManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class HttpClientProvider
 * Send messages over HTTP protocol
 * @package Deliveryman\ClientProvider
 */
class HttpClientProvider extends AbstractClientProvider
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * Sender constructor.
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getMasterConfig()
    {
        return $this->configManager->getConfiguration();
    }

//    /**
//     * @inheritdoc
//     */
//    public function send(RequestInterface $request, ?RequestMetaDataInterface $metaData)
//    {
//        // TODO: Implement send() method.
//        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
//    }

    /**
     * @inheritdoc
     */
    public function sendQueues(array $queues)
    {
        $config = $this->getMasterConfig();

        // TODO: various behavior on when:
        // - single queue - run normally
        // - multiple queues but single request per each - run in parallel
        // - multiple queues with various numbers of requests - run in forked scripts or implement queues consumers-receivers etc.
        // TODO: Implement sendQueue() method.
        die("\n" . __METHOD__ . ':' . __FILE__ . ':' . __LINE__ . "\n");
    }

}