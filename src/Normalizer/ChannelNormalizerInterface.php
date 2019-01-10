<?php
/**
 * Date: 10.01.19
 * Time: 18:03
 */

namespace Deliveryman\Normalizer;

use Deliveryman\Exception\SerializationException;

/**
 * Interface ChannelNormalizerInterface
 * Normalize data for BatchRequest's channel-specific data
 * @package Deliveryman\Normalizer
 */
interface ChannelNormalizerInterface
{
    /**
     * Checks whether the given class is supported for denormalization by this normalizer.
     *
     * @param $data
     * @param array $context Data context
     *
     * @return bool
     */
    public function supports($data, array $context = []): bool;

    /**
     * Denormalize BatchRequest data field
     * @param $data
     * @param array $context
     * @throws SerializationException
     * @return mixed
     */
    public function denormalizeData($data, array $context = []);

    /**
     * Denormalize BatchRequest config field
     * @param $config
     * @param array $context
     * @throws SerializationException
     * @return mixed
     */
    public function denormalizeConfig($config, array $context = []);
}