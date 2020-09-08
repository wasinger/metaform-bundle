<?php
namespace Wasinger\MetaformBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wasinger\MetaformBundle\DependencyInjection\AddTwigPathCompilerPass;

class WasingerMetaformBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new AddTwigPathCompilerPass());
    }
}
