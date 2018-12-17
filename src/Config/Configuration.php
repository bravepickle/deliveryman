<?php
/**
 * Date: 2018-12-13
 * Time: 00:02
 */

namespace Deliveryman\Config;


use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Name of configuration settings provided for given library
     */
    const CONFIG_NAME = 'deliveryman';

    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::CONFIG_NAME);

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->root(self::CONFIG_NAME);

        $rootNode->children()
            ->arrayNode('channels')
                ->requiresAtLeastOneElement()
                ->info('List of communication channels with their custom settings.')
                ->example(['http' => ['debug' => true, 'timeout' => 30, 'allow_redirects' => false]])
                ->defaultValue([
                    // TODO: how to specify optional http channel config format optionally using TreeBuilder? Dependency injection?
                    // TODO: group channel entities as module and put to same parent folder
                    'http' => [
                        // Guzzle's request options passed to Client object
                        'request_options' => [
                            'allow_redirects' => false,
                            'connect_timeout' => 10,
                            'timeout' => 30,
                            'debug' => false,
                        ],
                    ],
                ])

                ->arrayPrototype()->ignoreExtraKeys(false)->end()
            ->end()

            // TODO: use snake_case instead of camelCase in configs
            // TODO: move to channel config and add validator for those values to be used properly
            ->arrayNode('domains')
                ->requiresAtLeastOneElement()
                ->isRequired()
                ->info('Domains whitelist which are allowed for sending requests. Allowed formats: {domain}, {schema}://{domain}.')
                ->example(['example.com', 'http://an.example.com'])
                ->validate()
                    ->ifTrue(function($data) {
                        if (!is_array($data)) {
                            return true;
                        }

                        foreach ($data as $datum) {
                            if (!$datum || !preg_match('~^(?:https?://)?[^\s/]+$~i', $datum)) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->thenInvalid('Invalid format for whitelisted domains: %s')
                ->end()
                ->scalarPrototype()->end()
            ->end()

            ->enumNode('batch_format')
                ->values(['json', 'text', 'binary'])
                ->info('Input and output format for batch requests processing. ' .
                    'Options: json - will try to convert target response bodies to arrays, if formats match; ' .
                    'text - leave as it is; ' .
                    'binary - needs special processing for binary files.'
                )
                ->defaultValue('json')
            ->end()

            // TODO: move channel-related configs inside channel instance description
            // TODO: we need to specify input and output formats for third party resources. Input should support form-data
            ->enumNode('resource_format')
                ->values(['json', 'text', 'binary'])
                ->info('The format of returned data from requested resources by default.')
                ->defaultValue('json')
            ->end()

            ->enumNode('on_fail')
                ->values(['abort', 'proceed', 'abort-queue'])
                ->info('Select strategy on how to treat requests batch if one of them failed. ' .
                    'Options: abort - terminate execution of all not finished requests and return error; ' .
                    'proceed - disregard failed status of request and proceed with execution of the batch; ' .
                    'abort-queue - abort all not finished request in given queue sequence.'
                )
                ->defaultValue('abort')
            ->end()

            ->enumNode('config_merge')
                ->values(['first', 'unique', 'ignore'])
                ->info('Configs merge strategy specified in application and batch request (global scope, request level). ' .
                    'Options: first - select configuration specified closest to given request; ' .
                    'unique - merge global scope settings and request-level settings and leave uniquely ' .
                    'defined settings with preference given to request-level settings. ' .
                    'ignore - ignore all specified settings in batch requests body, look only for application config.'
                )
                ->defaultValue('first')
            ->end()

            ->arrayNode('expected_status_codes')
                ->info('List of all status codes that are considered OK, if returned. ' .
                    'If any other status codes received by requests, then request is considered as failed.')
                ->example([200, 422, 400])
                ->defaultValue([200, 201, 202, 204])
                ->requiresAtLeastOneElement()
                ->scalarPrototype()->end()
            ->end()

            // TODO: move to channel
            ->arrayNode('methods')
                ->info('Allowed methods for requests to send.')
                ->example(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])
                ->defaultValue(['GET', 'POST'])
                ->requiresAtLeastOneElement()
                ->scalarPrototype()->end()
            ->end()

            ->booleanNode('silent')
                ->info('If set to TRUE, then no batch responses content will be sent back to client. ' .
                    'Only status of batch request and errors will be shown.')
                ->defaultValue(false)
            ->end()

            // TODO: move to channel
            ->booleanNode('forward_master_headers')
                ->info('Pass all initial headers sent from client to batched requests. Headers are merged with ' .
                    'the rest specified bin batch request body.')
                ->defaultValue(true)
            ->end()

        ->end();

        return $treeBuilder;
    }
}