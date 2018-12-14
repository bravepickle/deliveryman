<?php

namespace Deliveryman\ClientProvider;


abstract class AbstractClientProvider implements ClientProviderInterface
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * Add error
     * @param $path
     * @param $message
     */
    protected function addError($path, $message)
    {
        $this->errors[] = ['path' => $path, 'message' => $message];
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
}