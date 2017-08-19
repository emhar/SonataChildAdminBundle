<?php

/*
 * This file is part of the EmharSonataChildAdminBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataChildAdminBundle;

use Emhar\SonataChildAdminBundle\DependencyInjection\CompilerPass\ReplaceControllerPass;
use Emhar\SonataChildAdminBundle\DependencyInjection\CompilerPass\ReplaceRouteGeneratorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritDoc}
 */
class EmharSonataChildAdminBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ReplaceControllerPass());
    }
}