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

use Ocubom\Twig\Extension\Svg\Loader\LoaderInterface;
use Ocubom\TwigExtraBundle\DataCollector\SvgTraceableLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SvgTraceableLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('data_collector.svg')) {
            return;
        }

        $this->addLoaderToCollector('ocubom_twig_extra.svg_loader', $container);

        /* @var LoaderInterface $loader */
        foreach ($container->findTaggedServiceIds('ocubom_twig_extra.svg_loader') as $ident => $tags) {
            $this->addLoaderToCollector($ident, $container);
        }
    }

    private function addLoaderToCollector(string $ident, ContainerBuilder $container): void
    {
        /** @var class-string $class */
        $class = $container->getDefinition($ident)->getClass();
        $class = new \ReflectionClass($class);

        // Convert to traceable loaders
        if (!$class->isSubclassOf(SvgTraceableLoader::class)) {
            $old = $container->getDefinition($ident);
            if ($old->isAbstract()) {
                return;
            }

            $new = new Definition(SvgTraceableLoader::class);
            $new->setTags($old->getTags());
            if (!$old->isPublic() || !$old->isPrivate()) {
                $new->setPublic($old->isPublic());
            }
            $new->setArguments([new Reference($newId = '.'.$ident.'.inner')]);

            foreach ($old->getMethodCalls() as [$call, $args]) {
                if (
                    'setCallbackWrapper' !== $call
                    || !$args[0] instanceof Definition
                    || !($args[0]->getArguments()[2] ?? null) instanceof Definition
                ) {
                    continue;
                }
                if ([new Reference($ident), 'setCallbackWrapper'] == $args[0]->getArguments()[2]->getFactory()) {
                    $args[0]->getArguments()[2]->setFactory([new Reference($newId), 'setCallbackWrapper']);
                }
            }

            $old->setTags([]);
            $old->setPublic(false);

            $new->setAutowired(true);
            $new->setAutoconfigured(true);
            $new->addTag('monolog.logger', ['channel' => 'twig_svg']);

            $container->setDefinition($newId, $old);
            $container->setDefinition($ident, $new);
        }

        // Tell the collector to add the new instance
        $collector = $container->getDefinition('data_collector.svg');
        $collector->addMethodCall('addLoader', [$ident, new Reference($ident)]);
        $collector->setPublic(false);
    }
}
