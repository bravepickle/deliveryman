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
                'headers' => [
                    ['name' => 'Accept-Language', 'value' => 'en_US'],
                ],
            ],
        ]);

        $this->assertEquals($config, [
            'domains' => ['example.com',],
            'headers' => [
                ['name' => 'Accept-Language', 'value' => 'en_US'],
            ],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'allow_redirects' => false,
                        'connect_timeout' => 10,
                        'timeout' => 30,
                        'debug' => false,
                        'http_errors' => false,
                    ],
                ],
            ],
            'batchFormat' => 'json',
            'resourceFormat' => 'json',
            'onFail' => 'abort',
            'configMerge' => 'first',
            'expectedStatusCodes' => [200, 201, 202, 204],
            'methods' => ['GET', 'POST'],
            'silent' => false,
            'forwardMasterHeaders' => true,
        ]);
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
            'deliveryman' => ['domains' => ['example.com']],
        ]);

        $this->assertEquals($config, [
            'domains' => ['example.com',],
            'channels' => [
                'http' => [
                    'request_options' => [
                        'allow_redirects' => false,
                        'connect_timeout' => 10,
                        'timeout' => 30,
                        'debug' => false,
                        'http_errors' => false,
                    ],
                ],
            ],
            'headers' => [],
            'batchFormat' => 'json',
            'resourceFormat' => 'json',
            'onFail' => 'abort',
            'configMerge' => 'first',
            'expectedStatusCodes' => [200, 201, 202, 204],
            'methods' => ['GET', 'POST'],
            'silent' => false,
            'forwardMasterHeaders' => true,
        ]);
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