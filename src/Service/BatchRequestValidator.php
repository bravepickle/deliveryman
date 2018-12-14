<?php

namespace Deliveryman\Service;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\Entity\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BatchRequestValidator
{
    const GENERAL_ERROR_PATH = '_general';
    const MSG_INVALID_EMPTY_QUEUES = 'Queues are not defined.';
    const MSG_EMPTY_URI = 'URI must be set.';
    const MSG_URI_NOT_ALLOWED = 'Given URI is not allowed.';
    const MSG_AMBIGUOUS_REQUEST_ALIAS = 'Request alias name must be unique.';

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var EventDispatcherInterface|null
     */
    protected $dispatcher;

    /**
     * Sender constructor.
     * @param ConfigManager $configManager
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(
        ConfigManager $configManager,
        ?EventDispatcherInterface $dispatcher = null
    ) {
        $this->configManager = $configManager;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param BatchRequest $batchRequest
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function validate(BatchRequest $batchRequest): array
    {
        $errors = [];

        $this->validateQueues($batchRequest, $errors);
//        $this->validateGeneralBatchConfig($batchRequest, $errors);
        $this->validateRequests($batchRequest, $errors);

        return $errors;
    }

    protected function validateQueues(BatchRequest $batchRequest, array &$errors)
    {
        if (empty($batchRequest->getQueues())) {
            $errors[self::GENERAL_ERROR_PATH][] = self::MSG_INVALID_EMPTY_QUEUES;
        }
    }

    /**
     * @param BatchRequest $batchRequest
     * @param array $errors
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function validateRequests(BatchRequest $batchRequest, array &$errors)
    {
        if (!$batchRequest->getQueues()) {
            return; // validate elsewhere
        }

        $config = $this->configManager->getConfiguration();
        $domains = $config['domains'];
        $allowedHostNames = []; // without protocol
        $allowedBaseUris = [];  // specified protocol and, probably, base path

        foreach ($domains as $domain) {
            if (preg_match('~^https?://.+~i', $domain)) {
                $allowedBaseUris[] = $domain;
            } else {
                $allowedHostNames[] = $domain;
            }
        }

        $aliases = [];
        foreach ($batchRequest->getQueues() as $queue) {
            /** @var Request $request */
            foreach ($queue as $request) {
                $this->validateUri($request, $allowedHostNames, $allowedBaseUris, $errors);

                $alias = $request->getAlias();
                if ($this->validateAlias($alias, $aliases, $errors)) {
                    $aliases[] = $alias;
                }
            }
        }
    }

    protected function validateAlias($alias, array $aliases, array &$errors): bool
    {
        if (in_array($alias, $aliases)) {
            $errors[$alias][] = self::MSG_AMBIGUOUS_REQUEST_ALIAS;

            return false;
        }

        return true;
    }

    protected function validateUri(Request $request, array $allowedHostNames, array $allowedBaseUris, array &$errors)
    {
        $path = $request->getAlias() ?: self::GENERAL_ERROR_PATH;
        if (!$request->getUri()) {
            $errors[$path][] = self::MSG_EMPTY_URI;

            return;
        }

        foreach ($allowedBaseUris as $baseUri) {
            if (stripos($request->getUri(), $baseUri) !== false) {
                return; // found match
            }
        }

        foreach ($allowedHostNames as $hostName) {
            $hostName = preg_quote($hostName, '~');
            if (preg_match('~^https?://' . $hostName . '~i', $request->getUri())) {
                return; // found match
            }
        }

        $errors[$path][] = self::MSG_URI_NOT_ALLOWED;
    }
}