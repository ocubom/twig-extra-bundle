<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\DependencyInjection\Compiler;

use Ocubom\TwigExtraBundle\Extensions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SuggestMissingExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('kernel.debug')) {
            $container->getDefinition('twig')
                ->addMethodCall(
                    'registerUndefinedFilterCallback',
                    [[Extensions::class, 'suggestFilter']]
                )
                ->addMethodCall(
                    'registerUndefinedFunctionCallback',
                    [[Extensions::class, 'suggestFunction']]
                )
            ;
        }
    }
}
