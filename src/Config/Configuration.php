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
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->children()
            ->arrayNode('domains')
                ->requiresAtLeastOneElement()
                ->isRequired()
                ->info('Domains whitelist which are allowed for sending requests. Allowed formats: {schema}://{domain}, {domain}.')
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

            ->arrayNode('headers')
                ->example([['name' => 'Content-Type', 'value' => 'application/json']])
                ->defaultValue([])
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('name')->cannotBeEmpty()->end()
                        ->scalarNode('value')->end()
                    ->end()
                ->end()
            ->end()

            ->enumNode('onFail')
                ->values(['abort', 'proceed', 'abort-sequence'])
                ->info('Select strategy on how to treat requests batch if one of them failed. ' .
                    'Options: abort - terminate execution of all not finished requests and return error; ' .
                    'proceed - disregard failed status of request and proceed with execution of the batch; ' .
                    'abort-sequence - abort all not finished request in given sequence.'
                )
                ->defaultValue('abort')
            ->end()

            ->enumNode('configMerge')
                ->values(['first', 'unique', 'ignore'])
                ->info('Configs merge strategy specified in application and batch request (global scope, request level). ' .
                    'Options: first - select configuration specified closest to given request; ' .
                    'unique - merge global scope settings and request-level settings and leave uniquely ' .
                    'defined settings with preference given to request-level settings. ' .
                    'ignore - ignore all specified settings in batch requests body, look only for application config.'
                )
                ->defaultValue('first')
            ->end()

            ->arrayNode('expectedStatusCodes')
                ->info('List of all status codes that are considered OK, if returned. ' .
                    'If any other status codes received by requests, then request is considered as failed.')
                ->example([200, 422, 400])
                ->defaultValue([200, 201, 202, 204])
                ->requiresAtLeastOneElement()
                ->scalarPrototype()->end()
            ->end()

            ->arrayNode('methods')
                ->info('Allowed methods for requests to send.')
                ->example(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])
                ->defaultValue(['GET', 'POST'])
                ->requiresAtLeastOneElement()
                ->scalarPrototype()->end()
            ->end()

            ->booleanNode('silent')
                ->info('If set to TRUE, then no batch responses content will be sent back to client. ' .
                    'Only status of batch execution')
                ->defaultValue(false)
            ->end()

        ->end();

        return $treeBuilder;
    }
}