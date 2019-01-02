<?php

namespace Deliveryman\Service;


use Deliveryman\Entity\BatchRequest;
use Deliveryman\EventListener\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BatchRequestValidator
{
    const GENERAL_ERROR_PATH = '_general';
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
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * Sender constructor.
     * @param ConfigManager $configManager
     * @param EventDispatcherInterface|null $dispatcher
     * @param ValidatorInterface|null $validator
     */
    public function __construct(
        ConfigManager $configManager,
        ?EventDispatcherInterface $dispatcher = null,
        ?ValidatorInterface $validator = null
    ) {
        $this->configManager = $configManager;
        $this->dispatcher = $dispatcher;
        $this->validator = $validator ?? Validation::createValidatorBuilder()
            ->addYamlMapping(__DIR__ . '/../Resources/config/validation.yaml')
            ->getValidator()
        ;
    }

    /**
     * @param BatchRequest $batchRequest
     * @param array|null $groups
     * @return array
     */
    public function validate(BatchRequest $batchRequest, ?array $groups = null): array
    {
        $errors = [];

        if ($this->dispatcher) {
            $event = new Event($batchRequest);
            $this->dispatcher->dispatch(Event::EVENT_PRE_VALIDATION, $event);
            $batchRequest = $event->getData();
        }

        $violations = $this->validator->validate($batchRequest, null, $groups);

        if ($this->dispatcher) {
            $event = new Event($violations);
            $this->dispatcher->dispatch(Event::EVENT_POST_VALIDATION, $event);
            $violations = $event->getData();
        }

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath() ?: self::GENERAL_ERROR_PATH;
            $errors[$path][] = $violation->getMessage();
        }

        ksort($errors, SORT_STRING);

        return $errors;
    }

//    /**
//     * @param BatchRequest $batchRequest
//     * @param array $errors
//     * @throws \Psr\Cache\InvalidArgumentException
//     */
//    protected function validateRequests(BatchRequest $batchRequest, array &$errors)
//    {
//        // todo: move to validation config
//        if (!$batchRequest->getData()) {
//            return; // validate elsewhere
//        }
//
//        $config = $this->configManager->getConfiguration();
//        $domains = $config['domains'];
//        $allowedHostNames = []; // without protocol
//        $allowedBaseUris = [];  // specified protocol and, probably, base path
//
//        foreach ($domains as $domain) {
//            if (preg_match('~^https?://.+~i', $domain)) {
//                $allowedBaseUris[] = $domain;
//            } else {
//                $allowedHostNames[] = $domain;
//            }
//        }
//
//        $aliases = [];
//        foreach ($batchRequest->getData() as $queue) {
//            /** @var Request $request */
//            foreach ($queue as $request) {
//                $this->validateUri($request, $allowedHostNames, $allowedBaseUris, $errors);
//
//                $alias = $request->getId();
//                if ($this->validateAlias($alias, $aliases, $errors)) {
//                    $aliases[] = $alias;
//                }
//            }
//        }
//    }
//
//    /**
//     * @param $alias
//     * @param array $aliases
//     * @param array $errors
//     * @return bool
//     */
//    protected function validateAlias($alias, array $aliases, array &$errors): bool
//    {
//        if (in_array($alias, $aliases)) {
//            $errors[$alias][] = self::MSG_AMBIGUOUS_REQUEST_ALIAS;
//
//            return false;
//        }
//
//        return true;
//    }
//
//    /**
//     * @param Request $request
//     * @param array $allowedHostNames
//     * @param array $allowedBaseUris
//     * @param array $errors
//     */
//    protected function validateUri(Request $request, array $allowedHostNames, array $allowedBaseUris, array &$errors)
//    {
//        $path = $request->getId() ?: self::GENERAL_ERROR_PATH;
//        if (!$request->getUri()) {
//            $errors[$path][] = self::MSG_EMPTY_URI;
//
//            return;
//        }
//
//        foreach ($allowedBaseUris as $baseUri) {
//            if (stripos($request->getUri(), $baseUri) !== false) {
//                return; // found match
//            }
//        }
//
//        foreach ($allowedHostNames as $hostName) {
//            $hostName = preg_quote($hostName, '~');
//            if (preg_match('~^https?://' . $hostName . '($|/)~i', $request->getUri())) {
//                return; // found match
//            }
//        }
//
//        $errors[$path][] = self::MSG_URI_NOT_ALLOWED;
//    }
}