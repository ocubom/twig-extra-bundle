<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle;

use Ocubom\TwigExtraBundle\DependencyInjection\Compiler\SuggestMissingExtensionPass;
use Ocubom\TwigExtraBundle\DependencyInjection\Compiler\SvgTraceableLoaderPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OcubomTwigExtraBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SuggestMissingExtensionPass());
        $container->addCompilerPass(new SvgTraceableLoaderPass());
    }
}
