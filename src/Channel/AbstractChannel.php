<?php

namespace Deliveryman\Channel;


use Psr\Http\Message\ResponseInterface;

abstract class AbstractChannel implements ChannelInterface
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array|ResponseInterface[]
     */
    protected $failedResponses = [];

    /**
     * @var array|ResponseInterface[]
     */
    protected $okResponses = [];

    /**
     * Add error
     * @param $path
     * @param $message
     * @return $this
     */
    public function addError($path, $message)
    {
        $this->errors[$path] = $message;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @inheritdoc
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * @inheritdoc
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @inheritdoc
     */
    public function hasFailedResponses(): bool
    {
        return !empty($this->failedResponses);
    }

    /**
     * @return array|ResponseInterface[]
     */
    public function getFailedResponses(): array
    {
        return $this->failedResponses;
    }

    /**
     * Add failed response
     * @param $path
     * @param ResponseInterface $response
     * @return $this
     */
    public function addFailedResponse($path, ResponseInterface $response)
    {
        $this->failedResponses[$path] = $response;

        return $this;
    }

    /**
     * Add succeeded response
     * @param $path
     * @param ResponseInterface $response
     * @return $this
     */
    public function addOkResponse($path, ResponseInterface $response)
    {
        $this->okResponses[$path] = $response;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasOkResponses(): bool
    {
        return !empty($this->okResponses);
    }

    /**
     * @return array|ResponseInterface[]
     */
    public function getOkResponses(): array
    {
        return $this->okResponses;
    }

    public function clearOkResponses(): void
    {
        $this->okResponses = [];
    }

    public function clearFailedResponses(): void
    {
        $this->failedResponses = [];
    }

    public function clear(): void
    {
        $this->clearErrors();
        $this->clearFailedResponses();
        $this->clearOkResponses();
    }


}