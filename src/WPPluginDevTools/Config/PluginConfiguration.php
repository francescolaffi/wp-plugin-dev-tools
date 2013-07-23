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

        $nodes->scalarNode('svn_repo_base')->defaultValue('http://plugins.svn.wordpress.org');
        $nodes->scalarNode('svn_repo')->defaultNull();

        $nodes->arrayNode('version_locations')
            ->useAttributeAsKey('file')
            ->prototype('scalar')->end();

        $nodes->scalarNode('pot_location');

        $nodes->arrayNode('exclude')
            ->prototype('scalar')->end();
        $nodes->booleanNode('use_default_exclude_list')->defaultTrue();

        $trunkReadmeContent = <<<TXT
=== %name% ===
Stable tag: %version%

Development version can be found at %dev-url%
TXT;

        $nodes->arrayNode('trunk')
            ->canBeDisabled()
            ->children()
                ->booleanNode('minimal')->defaultTrue()->end()
                ->scalarNode('dev_url')->defaultNull()->end()
                ->scalarNode('readme_content')->defaultValue($trunkReadmeContent)->end()
            ->end();

        $nodes->arrayNode('assets')
            ->canBeDisabled()
            ->children()
                ->scalarNode('dir')->defaultValue('repo-assets')->end()
            ->end();

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
