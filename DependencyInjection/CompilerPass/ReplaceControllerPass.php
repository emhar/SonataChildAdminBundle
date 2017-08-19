<?php

/*
 * This file is part of the EmharSonataChildAdminBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataChildAdminBundle\DependencyInjection\CompilerPass;

use Emhar\SonataChildAdminBundle\Controller\HelperController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * {@inheritDoc}
 */
class ReplaceControllerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('sonata.admin') as $id => $tags) {
            $definition = $container->getDefinition($id);
            if ($definition->getArgument(2) === 'SonataAdminBundle:CRUD') {
                $definition->replaceArgument(2, 'EmharSonataChildAdminBundle:CRUD');
            }
        }
        $definition = $container->getDefinition('sonata.admin.controller.admin');
        $definition->setClass(HelperController::class);
    }
}
