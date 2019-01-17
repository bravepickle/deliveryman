<?php
/**
 * Date: 2018-12-22
 * Time: 17:27
 */

namespace Deliveryman\Entity;

/**
 * Interface IdentifiableInterface provides contract this entity can be identified by alias
 * @package Deliveryman\Entity
 */
interface IdentifiableInterface
{
    /**
     * Return identifier of response
     * @return mixed
     */
    public function getId();
}