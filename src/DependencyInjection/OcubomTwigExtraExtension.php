<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\DependencyInjection;

use Ocubom\Twig\Extension\HtmlAttributesRuntime;
use Ocubom\Twig\Extension\HtmlCompressRuntime;
use Ocubom\Twig\Extension\HtmlExtension;
use Ocubom\Twig\Extension\Svg\Finder;
use Ocubom\Twig\Extension\Svg\FinderInterface;
use Ocubom\Twig\Extension\SvgExtension;
use Ocubom\Twig\Extension\SvgRuntime;
use Ocubom\TwigExtraBundle\Extensions;
use Ocubom\TwigExtraBundle\Listener\AddHttpHeadersListener;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class OcubomTwigExtraExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        assert($configuration instanceof ConfigurationInterface);
        $config = $this->processConfiguration($configuration, $configs);

        foreach (array_keys(Extensions::getClasses()) as $name) {
            if ($this->isConfigEnabled($container, $config[$name])) {
                $this->{'registerTwig'.ucfirst($name).'Extension'}($container, $config[$name]);
            }
        }

        $this->registerHttpHeadersListener($container, $config);
    }

    private function registerTwigHtmlExtension(ContainerBuilder $container, array $config): void
    {
        if ($config['enabled']) {
            $container->register('ocubom_twig_extra.extension.html', HtmlExtension::class)
                ->addTag('twig.extension');

            $container->register('ocubom_twig_extra.runtime.html_attributes', HtmlAttributesRuntime::class)
                ->addTag('twig.runtime');

            $container->register('ocubom_twig_extra.runtime.html_compress', HtmlCompressRuntime::class)
                ->setArguments([
                    $config['compression']['force'],
                    $config['compression']['level'],
                ])
                ->addTag('twig.runtime');
        }
    }

    private function registerTwigSvgExtension(ContainerBuilder $container, array $config): void
    {
        if ($config['enabled']) {
            $container->register('ocubom_twig_extra.extension.svg', SvgExtension::class)
                ->addTag('twig.extension');

            $container->register('ocubom_twig_extra.runtime.svg', SvgRuntime::class)
                ->setArguments([
                    new Reference('ocubom_twig_extra.service.svg_finder'),
                    new Reference('logger'),
                ])
                ->addTag('twig.runtime');

            $container->register('ocubom_twig_extra.service.svg_finder', Finder::class)
                ->setArguments($config['search_path']);

            $container->setAlias(FinderInterface::class, 'ocubom_twig_extra.service.svg_finder');
        }
    }

    private function registerHttpHeadersListener(ContainerBuilder $container, array $config): void
    {
        // Filter enabled header rules
        $headers = array_filter($config['http_headers'], function (array $header): bool {
            return $header['enabled'] ? true : false;
        });

        // Only register listener if some rule is defined
        if (count($headers) > 0) {
            $container->register('ocubom_twig_extra.listener.http_headers', AddHttpHeadersListener::class)
                ->setArguments(array_values($headers))
                ->addTag('kernel.event_subscriber');
        }
    }
}
