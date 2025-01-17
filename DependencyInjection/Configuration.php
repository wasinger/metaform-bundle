<?php
namespace Wasinger\MetaformBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('wasinger_metaform');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('form_dir')->isRequired()->end()
                ->scalarNode('upload_dir')->isRequired()->end()
                ->booleanNode('isometriks_spam_honeypot')->defaultFalse()->end()
                ->booleanNode('isometriks_spam_timed')->defaultFalse()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}