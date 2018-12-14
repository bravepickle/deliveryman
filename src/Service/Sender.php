<?php

namespace Deliveryman\Service;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Exception\SendingException;

/**
 * Class Sender
 * Is a facade for sending parsed batch request
 * @package Deliveryman\Service
 */
class Sender
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
     * Process batch request queries
     * @param BatchRequest $batchRequest
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws SendingException
     */
    public function send(BatchRequest $batchRequest)
    {
        // 0. check global config settings
        // 1. loop through queues to send requests in parallel
        // 2. merge configs according to settings per request
        // 3. create queues and process them accordingly
        // 4. dispatch events per requests, queues etc.

        if (!$batchRequest->getQueues()) {
            throw new SendingException('No queues with requests specified to process.');
        }
        
        $masterConfig = $this->getMasterConfig();

        foreach ($batchRequest->getQueues() as $queue) {
            foreach ($queue as $requestItem) {
                
            }
        }

    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getMasterConfig()
    {
        return $this->configManager->getConfiguration();
    }
}