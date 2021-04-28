<?php


namespace Wasinger\MetaformBundle;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Class FormConfigurationDefinition
 *
 * This is where the configuration tree for form configurations is defined.
 *
 * @package Wasinger\MetaformBundle
 */
class MetaformConfiguration implements ConfigurationInterface
{
    private $levelcount = 0;

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('form');

        $treeBuilder->getRootNode()
            ->fixXmlConfig('option')
            ->children()
                ->scalarNode('title')->isRequired()->end()
                ->scalarNode('id')->defaultNull()->end()
                ->booleanNode('disabled')->defaultFalse()->end()
                ->append($this->addOptionsNode())
                ->append($this->addElementsNode(true))
                ->append($this->addMailNode())
            ->end()
        ;
        return $treeBuilder;
    }
    public function addElementsNode(bool $required = false)
    {
        $treebuilder = new TreeBuilder('elements');
        $node = $treebuilder->getRootNode()
            ->fixXmlConfig('element')
            ->useAttributeAsKey('name')
//            ->isRequired()
//            ->requiresAtLeastOneElement()
//            ->arrayPrototype()
//                ->children()
//                    ->scalarNode('type')->defaultValue(TextType::class)->end()
//                    ->booleanNode('required')->defaultFalse()->end()
//                    ->scalarNode('label')->end()
//                    ->arrayNode('choices')
//                        ->scalarPrototype()->end()
//                    ->end()
//                    ->variableNode('options')->end()
//                    ->append($this->addElementsNode(false))
//                ->end()
//            ->end()
        ;
        $children = $node->arrayPrototype()->children();
        $children
            ->scalarNode('type')->defaultValue(TextType::class)->end()
            ->booleanNode('required')->defaultFalse()->end()
            ->scalarNode('label')->end()
            ->scalarNode('maxlength')->end()
            ->arrayNode('choices')
                ->scalarPrototype()->end()
            ->end()
            ->variableNode('options')->end()
        ;

        // An element can have child elements, up to two levels
        if ($this->levelcount < 2) {
            $this->levelcount++;
            $children->append($this->addElementsNode(false));
        }

        if ($required) {
            $node->isRequired()->requiresAtLeastOneElement();
        }
        return $node;
    }

    public function addOptionsNode()
    {
        $treebuilder = new TreeBuilder('options');
        return $treebuilder->getRootNode()
            ->children()
                ->scalarNode('text_pre')->end()
                ->scalarNode('text_post')->end()
                ->scalarNode('valid_from')->end()
                ->scalarNode('valid_until')->end()
                ->booleanNode('horizontal_data_table')->defaultFalse()->end()
                ->scalarNode('text_pre_submitted')->end()
                ->scalarNode('text_post_submitted')->end()
                ->scalarNode('response_text_only')->end()
                ->arrayNode('affirmations')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;
    }

    public function addMailNode()
    {
        $treebuilder = new TreeBuilder('mail');
        return $treebuilder->getRootNode()
            ->isRequired()
            ->children()
                ->arrayNode('to')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return [$v]; })
                    ->end()
                    ->requiresAtLeastOneElement()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->arrayNode('from')
                    ->beforeNormalization()
                        ->ifString()
                            ->then(function ($v) { return [$v]; })
                        ->end()
                    ->requiresAtLeastOneElement()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->arrayNode('cc')
                    ->beforeNormalization()
                        ->ifString()
                            ->then(function ($v) { return [$v]; })
                        ->end()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->arrayNode('bcc')
                    ->beforeNormalization()
                        ->ifString()
                            ->then(function ($v) { return [$v]; })
                        ->end()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->scalarNode('subject')->isRequired()->end()
                ->booleanNode('copy_to_sender')->end()
                ->scalarNode('senderfield')->end()
                ->scalarNode('text_pre')->end()
                ->scalarNode('text_post')->end()
                ->scalarNode('text_pre_sender')->end()
                ->scalarNode('text_post_sender')->end()
            ->end()
        ;
    }

}