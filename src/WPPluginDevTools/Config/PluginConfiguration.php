<?php

namespace WPPluginDevTools\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class PluginConfiguration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $nodes = $treeBuilder->root('plugin')->children();
        $nodes->scalarNode('slug')
            ->validate()
                ->ifTrue($this->ifNotMatch('/^[a-z]+(-[a-z]+)*$/i'))
                ->thenInvalid('Invalid plugin slug %s')
            ->end()
            ->isRequired();
        $nodes->scalarNode('svn_repo')->defaultValue('http://plugins.svn.wordpress.org');
        $nodes->arrayNode('version_locations')
            ->useAttributeAsKey('file')
            ->prototype('scalar')->end();
        $nodes->scalarNode('pot_location');
        $nodes->arrayNode('exclude')
            ->prototype('scalar')->end();
        $nodes->booleanNode('use_default_exclude_list')->defaultTrue();

        return $treeBuilder;
    }

    private function ifMatch($pattern)
    {
        return function ($v) use ($pattern) {
            return 1 === preg_match($pattern, $v, $match);
        };
    }

    private function ifNotMatch($pattern)
    {
        return function ($v) use ($pattern) {
            return 1 !== preg_match($pattern, $v, $match);
        };
    }
}
