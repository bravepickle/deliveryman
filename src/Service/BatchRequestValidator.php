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
     * BatchRequestHandler constructor.
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
}