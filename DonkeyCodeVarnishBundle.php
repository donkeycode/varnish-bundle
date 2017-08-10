<?php

namespace DonkeyCode\VarnishBundle;

use DonkeyCode\VarnishBundle\DependencyInjection\Security\Factory\VarnishFactory;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use DonkeyCode\VarnishBundle\DependencyInjection\Compiler\AddPropelTagPass;

class DonkeyCodeVarnishBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddPropelTagPass());

        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new VarnishFactory());
    } 
}