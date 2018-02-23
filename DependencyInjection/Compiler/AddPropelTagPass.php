<?php

namespace DonkeyCode\VarnishBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;

/**
 * Add tagged provider to the hash generator for user context.
 */
class AddPropelTagPass implements CompilerPassInterface
{
    const TAGGED_SERVICE = 'varnish.httpcache.tag_listener';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(self::TAGGED_SERVICE)) {
            return;
        }

        $definition = $container->getDefinition(self::TAGGED_SERVICE);

        foreach ($this->getPropelModels($container) as $model) {
            $definition->addTag("propel.event_subscriber", [
                'class' => $model
            ]);
        }
    }

    protected function getPropelModels(ContainerBuilder $container)
    {
        $classes = [];

        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->notName('*Query.php')
            ->depth(0)
            ->in($container->getParameter('kernel.root_dir').'/../src/*/*Bundle/Propel')
            ;

        foreach ($files as $file) {
            list ($fold, $path) = explode('src/', $file->getPathname());

            $path = preg_replace('/(.+)\.php/', '$1', $path);
            $path = str_replace('/', '\\', $path);

            $base = str_replace('Propel', 'Propel\\Base', $path);
            if (class_exists($base)) {
                $classes[] = $path;
            }
        }

        return $classes;
    }
}
