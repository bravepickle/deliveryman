<?php
/**
 * Date: 2018-12-13
 * Time: 00:15
 */

namespace DeliverymanTest\Config;


use Deliveryman\Config\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * Class DefinitionTest
 * Testing definition configuration with default values
 * @package DeliverymanTest\Config
 */
class ConfigurationTest extends TestCase
{
    /**
     * Test that configuration is properly working when required settings are filled in
     */
    public function testUpdated()
    {
        $definition = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($definition, [
            'deliveryman' => [
                'domains' => ['example.com'],
            ],
        ]);

        $this->assertEquals([
            'domains' => ['example.com',],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'allow_redirects' => false,
                        'connect_timeout' => 10,
                        'timeout' => 30,
                        'debug' => false,
                    ],
                    'sender_headers' => [],
                    'receiver_headers' => [],
                ],
            ],
            'batch_format' => 'json',
            'resource_format' => 'json',
            'on_fail' => 'abort',
            'config_merge' => 'first',
            'expected_status_codes' => [200, 201, 202, 204],
            'methods' => ['GET', 'POST'],
            'silent' => false,
            'forward_master_headers' => true,
        ], $config);
    }

    /**
     * Test that exception is thrown because of unset required values
     */
    public function testDefaultsRequired()
    {
        $this->expectException(InvalidConfigurationException::class);
        $definition = new Configuration();

        $processor = new Processor();
        $processor->processConfiguration($definition, []);
    }

    /**
     * Test that configuration is properly working when required settings are filled in
     */
    public function testRequiredSet()
    {
        $definition = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($definition, [
            'deliveryman' => [
                'domains' => ['example.com'],
            ],
        ]);

        $this->assertEquals([
            'domains' => ['example.com',],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'allow_redirects' => false,
                        'connect_timeout' => 10,
                        'timeout' => 30,
                        'debug' => false,
                    ],
                    'sender_headers' => [],
                    'receiver_headers' => [],
                ],
            ],
            'batch_format' => 'json',
            'resource_format' => 'json',
            'on_fail' => 'abort',
            'config_merge' => 'first',
            'expected_status_codes' => [200, 201, 202, 204],
            'methods' => ['GET', 'POST'],
            'silent' => false,
            'forward_master_headers' => true,
        ], $config);
    }

    /**
     * Test that configuration is properly working when required settings are filled in
     */
    public function testInvalidData()
    {
        $this->expectException(InvalidConfigurationException::class);

        $definition = new Configuration();
        $processor = new Processor();
        $processor->processConfiguration($definition, [
            'deliveryman' => ['domains' => ['i n v a l i d example.com']],
        ]);
    }

}