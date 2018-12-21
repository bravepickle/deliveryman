<?php
/**
 * Date: 2018-12-13
 * Time: 00:02
 */

namespace Deliveryman\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Name of configuration settings provided for given library
     * @var string
     */
    protected $name = 'deliveryman';

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder($this->name);

        $this->buildNodesTree($treeBuilder);

        // TODO: move to channel config and add validator for those values to be used properly
        // TODO: move channel-related configs inside channel instance description
        // TODO: we need to specify input and output formats for third party resources. Input should support form-data

        return $treeBuilder;
    }

    /**
     * @param NodeBuilder $rootNode
     */
    protected function addChannelsBranch(NodeBuilder $rootNode): void
    {
        $defaultValues = [ // TODO: update defaults after all options will be reconfigured, add missing
            'http_queue' => [
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
                ->arrayNode('http_queue')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('request_options')
                            ->isRequired()
                            ->info('Request options for Guzzle client library')
                            ->defaultValue($defaultValues['http_queue']['request_options'])
                            ->variablePrototype()->end()
                        ->end()
                        ->arrayNode('sender_headers')
                            ->info('Pass initial request headers from sender to receiver. ' .
                                'If set to NULL or FALSE then no headers will be forwarded.')
                            ->beforeNormalization()->castToArray()->end()
                            ->defaultValue([])
                            ->treatNullLike([])
                            ->example(['Origin', 'Cookie', 'Authorization'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->arrayNode('receiver_headers')
                            ->beforeNormalization()->castToArray()->end()
                            ->info('Pass response headers from receiver to sender. If set to TRUE then ' .
                                'all receiver headers will be displayed to sender inside batch response body')
                            ->defaultValue([])
                            ->example(['Set-Cookie'])
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->arrayNode('expected_status_codes')
                            ->info('List of all status codes that are considered OK, if returned. ' .
                                'If any other status codes received by requests, then request is considered as failed.')
                            ->example([200, 422, 400])
                            ->defaultValue([200, 201, 202, 204])
                            ->requiresAtLeastOneElement()
                            ->scalarPrototype()->end()
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
    protected function addSilentLeaf(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('silent')
            ->info('If set to TRUE, then no batch responses content will be sent back to client. ' .
                'Only status of batch request and errors will be shown.')
            ->defaultValue(false)
        ->end();
    }

    /**
     * @param TreeBuilder $treeBuilder
     * @return NodeDefinition
     */
    public function buildNodesTree(TreeBuilder $treeBuilder): NodeDefinition
    {
        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // is workaround to support symfony/config 4.1 and older
            $rootNode = $treeBuilder->root($this->name);
        }

        $nodeBuilder = $rootNode->addDefaultsIfNotSet()->children();

        $this->addDomainsBranch($nodeBuilder);
        $this->addChannelsBranch($nodeBuilder);
        $this->addBatchFormatLeaf($nodeBuilder);
        $this->addResourceFormatLeaf($nodeBuilder);
        $this->addOnFailLeaf($nodeBuilder);
        $this->addConfigMergeLeaf($nodeBuilder);
        $this->addSilentLeaf($nodeBuilder);

        return $nodeBuilder->end();
    }
}