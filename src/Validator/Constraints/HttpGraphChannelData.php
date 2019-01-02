<?php
/**
 * Date: 2019-01-02
 * Time: 20:34
 */

namespace Deliveryman\Validator\Constraints;


use Symfony\Component\Validator\Constraint;

/**
 * Class HttpGraphChannelData
 * @package Deliveryman\Validator\Constraints
 */
class HttpGraphChannelData extends Constraint
{
    public $messageRequestExpected = 'Expecting to have HTTP request. Received "{{ type }}".';
    public $messageRequestIdAmbiguous = 'HTTP request ID "{{ id }}" is ambiguous.';
    public $messageRequestRefIdNotExist = 'HTTP request reference by ID "{{ id }}" does not exist.';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}