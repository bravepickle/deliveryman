<?php
/**
 * Date: 2018-12-13
 * Time: 00:02
 */

namespace Deliveryman\Config;


use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
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

        $nodeBuilder = $rootNode->children();

        $this->addDomainsBranch($nodeBuilder);
        $this->addChannelsBranch($nodeBuilder);
        $this->addBatchFormatLeaf($nodeBuilder);
        $this->addResourceFormatLeaf($nodeBuilder);
        $this->addOnFailLeaf($nodeBuilder);
        $this->addConfigMergeLeaf($nodeBuilder);
        $this->addExpStatCodesLeaf($nodeBuilder);
        $this->addMethodsLeaf($nodeBuilder);
        $this->addSilentLeaf($nodeBuilder);
        $this->addForwardedHeadersLeaf($nodeBuilder);

        $nodeBuilder->end();

        // TODO: move to channel config and add validator for those values to be used properly
        // TODO: move channel-related configs inside channel instance description
        // TODO: we need to specify input and output formats for third party resources. Input should support form-data
        // TODO: move to channel

        return $treeBuilder;
    }

    /**
     * @param NodeBuilder $rootNode
     */
    protected function addChannelsBranch(NodeBuilder $rootNode): void
    {
        $defaultValues = [
            'http' => [
                'request_options' => [
                    'allow_redirects' => false,
                    'connect_timeout' => 10,
                    'timeout' => 30,
                    'debug' => false,
                ],
            ],
        ];

        $rootNode->arrayNode('channels')
            ->info('List of predefined communication channels with their settings.')
            ->addDefaultsIfNotSet()
            ->treatFalseLike($defaultValues)
            ->treatTrueLike($defaultValues)
            ->treatNullLike($defaultValues)
            ->children()
                ->arrayNode('http')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('request_options')
                            ->isRequired()
                            ->info('Request options for Guzzle client library')
                            ->defaultValue($defaultValues['http']['request_options'])
                            ->variablePrototype()->end()
                        ->end()
                        ->arrayNode('sender_headers')
                            ->info('Pass initial request headers from sender to receiver. ' .
                                'If set to NULL or FALSE then no headers will be forwarded.')
                            ->defaultValue([])
                            ->treatNullLike([])
                            ->example(['Origin', 'Cookie', 'Authorization'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->arrayNode('receiver_headers')
                            ->info('Pass response headers from receiver to sender. If set to TRUE then ' .
                                'all receiver headers will be displayed to sender inside batch response body')
                            ->defaultValue([])
                            ->example(['Set-Cookie'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
    }

    /**
     * @param NodeBuilder $rootNode
     */
    protected function addDomainsBranch(NodeBuilder $rootNode): void
    {
        $rootNode->arrayNode('domains')
            ->beforeNormalization()->castToArray()->end()
            ->requiresAtLeastOneElement()
            ->isRequired()
            ->info('Domains whitelist which are allowed for sending requests. Allowed formats: {domain}, {schema}://{domain}.')
            ->example(['example.com', 'http://an.example.com'])
            ->validate()
            ->ifTrue(function ($data) {
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
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addBatchFormatLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->enumNode('batch_format')
            ->values(['json', 'text', 'binary'])
            ->info('Input and output format for batch requests processing. ' .
                'Options: json - will try to convert target response bodies to arrays, if formats match; ' .
                'text - leave as it is; ' .
                'binary - needs special processing for binary files.'
            )
            ->defaultValue('json')
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addResourceFormatLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->enumNode('resource_format')
            ->values(['json', 'text', 'binary'])
            ->info('The format of returned data from requested resources by default.')
            ->defaultValue('json')
            ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addOnFailLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->enumNode('on_fail')
            ->values(['abort', 'proceed', 'abort-queue'])
            ->info('Select strategy on how to treat requests batch if one of them failed. ' .
                'Options: abort - terminate execution of all not finished requests and return error; ' .
                'proceed - disregard failed status of request and proceed with execution of the batch; ' .
                'abort-queue - abort all not finished request in given queue sequence.'
            )
            ->defaultValue('abort')
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addConfigMergeLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->enumNode('config_merge')
            ->values(['first', 'unique', 'ignore'])
            ->info('Configs merge strategy specified in application and batch request (global scope, request level). ' .
                'Options: first - select configuration specified closest to given request; ' .
                'unique - merge global scope settings and request-level settings and leave uniquely ' .
                'defined settings with preference given to request-level settings. ' .
                'ignore - ignore all specified settings in batch requests body, look only for application config.'
            )
            ->defaultValue('first')
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addExpStatCodesLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->arrayNode('expected_status_codes')
            ->info('List of all status codes that are considered OK, if returned. ' .
                'If any other status codes received by requests, then request is considered as failed.')
            ->example([200, 422, 400])
            ->defaultValue([200, 201, 202, 204])
            ->requiresAtLeastOneElement()
            ->scalarPrototype()->end()
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addMethodsLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->arrayNode('methods')
            ->info('Allowed methods for requests to send.')
            ->example(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])
            ->defaultValue(['GET', 'POST'])
            ->requiresAtLeastOneElement()
            ->scalarPrototype()->end()
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addSilentLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('silent')
            ->info('If set to TRUE, then no batch responses content will be sent back to client. ' .
                'Only status of batch request and errors will be shown.')
            ->defaultValue(false)
        ->end();
    }

    /**
     * @param NodeBuilder $nodeBuilder
     */
    protected function addForwardedHeadersLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('forward_master_headers')
            ->info('Pass all initial headers sent from client to batched requests. Headers are merged with ' .
                'the rest specified bin batch request body.')
            ->defaultValue(true)
        ->end();
    }
}