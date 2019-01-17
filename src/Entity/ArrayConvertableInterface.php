<?php
/**
 * Date: 2018-12-26
 * Time: 16:14
 */

namespace Deliveryman\Entity;


interface ArrayConvertableInterface
{
    /**
     * Convert given instance of a class to array
     * with properties as values
     * @return array
     */
    public function toArray(): array;

    /**
     * Load object data from array and set values
     * @param array $data
     * @param array $context
     */
    public function load(array $data, $context = []): void;
}