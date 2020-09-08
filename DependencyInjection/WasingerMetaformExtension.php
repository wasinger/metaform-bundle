<?php
namespace Wasinger\MetaformBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader;

class WasingerMetaformExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('wasinger_metaform.upload_dir', $config['upload_dir']);
        $container->setParameter('wasinger_metaform.form_dir', $config['form_dir']);

//        $container->loadFromExtension('twig', [
//            'paths' => [
//                $config['form_dir'] => 'forms'
//            ]
//        ]);

        $loader = new Loader\YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yml');
    }

}
