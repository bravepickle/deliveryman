<?php

namespace Deliveryman\Entity;


class BatchResponse
{
    /**
     * All requests completed successfully
     */
    const STATUS_SUCCESS = 'ok';

    /**
     * Execution of requests was aborted due to unrecoverable errors during execution
     */
    const STATUS_ABORTED = 'aborted';

    /**
     * Some requests finished with errors, but the rest was processed
     */
    const STATUS_FAILED = 'failed';

    /**
     * Response data for batch request
     * @var array|null
     */
    protected $data;

    /**
     * Errors listed here for processing responses
     * @var array|null
     */
    protected $errors;

    /**
     * Textual representation of batch response's status
     * @var string|null
     */
    protected $status;

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array|null $data
     * @return BatchResponse
     */
    public function setData(?array $data): BatchResponse
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string|null $status
     * @return BatchResponse
     */
    public function setStatus(?string $status): BatchResponse
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @param array|null $errors
     * @return BatchResponse
     */
    public function setErrors(?array $errors): BatchResponse
    {
        $this->errors = $errors;

        return $this;
    }

}