<?php

namespace WPPluginDevTools\Config;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Yaml\Yaml;

class YamlPluginLoader extends Loader
{
    /**
     * Loads a resource.
     *
     * @param  mixed                     $file The resource
     * @param  string                    $type The resource type
     * @throws \UnexpectedValueException
     * @return array
     */
    public function load($file, $type = null)
    {
        if (!is_file($file)) {
            throw new \UnexpectedValueException('Unable to find file ' . $file);
        }

        $processor = new Processor();
        $configuration = new PluginConfiguration();
        $config = Yaml::parse($file);

        return $processor->processConfiguration($configuration, array($config));
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'yml' === pathinfo($resource, PATHINFO_EXTENSION);
    }
}
