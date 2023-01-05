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
use Ocubom\Twig\Extension\Svg\Library\FontAwesome\Finder as FontAwesomeFinder;
use Ocubom\Twig\Extension\Svg\Library\FontAwesomeRuntime;
use Ocubom\Twig\Extension\SvgExtension;
use Ocubom\Twig\Extension\SvgRuntime;
use Ocubom\TwigExtraBundle\Extensions;
use Ocubom\TwigExtraBundle\Listener\AddHttpHeadersListener;
use Ocubom\TwigExtraBundle\Twig\WebpackEncoreExtension;
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
                $call = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
                $this->{'load'.$call}($container, $config[$name]);
            }
        }

        $this->loadHttpHeaders($container, $config['http_headers']);
    }

    private function loadHttpHeaders(ContainerBuilder $container, array $config): void
    {
        // Filter enabled header rules
        $headers = array_filter($config, function (array $header): bool {
            return $header['enabled'] ? true : false;
        });

        // Only register listener if some rule is defined
        if (count($headers) > 0) {
            $container->register('ocubom_twig_extra.http_headers_listener', AddHttpHeadersListener::class)
                ->setArguments(array_values($headers))
                ->addTag('kernel.event_subscriber');
        }
    }

    private function loadHtml(ContainerBuilder $container, array $config): void
    {
        $container->register('ocubom_twig_extra.twig_html_extension', HtmlExtension::class)
            ->addTag('twig.extension');

        $container->register('ocubom_twig_extra.twig_html_attributes_runtime', HtmlAttributesRuntime::class)
            ->addTag('twig.runtime');

        $container->register('ocubom_twig_extra.twig_html_compress_runtime', HtmlCompressRuntime::class)
            ->setArguments([
                $config['compression']['force'],
                $config['compression']['level'],
            ])
            ->addTag('twig.runtime');
    }

    private function loadSvg(ContainerBuilder $container, array $config): void
    {
        $container
            ->register('ocubom_twig_extra.twig_svg_extension', SvgExtension::class)
            ->addTag('twig.extension');

        foreach ($config['finders'] as $name => $paths) {
            $hash = sha1(serialize($paths));
            $key = ".ocubom_twig_extra.svg.finder.{$hash}";

            // Register finder if not exists
            if (!$container->has($key)) {
                $container
                    ->register($key, Finder::class)
                    ->setArguments($paths)
                    ->setPublic(false);
            }

            // Create a hidden alias
            $container->setAlias(".ocubom_twig_extra.svg.{$name}_finder.inner", $key);
        }

        // Register default runtime
        if ($container->has('.ocubom_twig_extra.svg.default_finder.inner')) {
            // Register runtime
            $container
                ->register('ocubom_twig_extra.twig_svg_runtime', SvgRuntime::class)
                ->setArguments([
                    new Reference('ocubom_twig_extra.svg.default_finder'),
                    new Reference('logger'),
                ])
                ->addTag('twig.runtime');

            // Create default finder (just an alias)
            $container->setAlias('ocubom_twig_extra.svg.default_finder', '.ocubom_twig_extra.svg.default_finder.inner');

            // Create class aliases
            $container->setAlias(FinderInterface::class, 'ocubom_twig_extra.svg.default_finder');
        }

        // Register fontawesome runtime
        if ($container->has('.ocubom_twig_extra.svg.fontawesome_finder.inner')) {
            // Register runtime
            $container
                ->register('ocubom_twig_extra.twig_fontawesome_runtime', FontAwesomeRuntime::class)
                ->setArguments([
                    new Reference('ocubom_twig_extra.svg.fontawesome_finder'),
                    new Reference('logger'),
                ])
                ->addTag('twig.runtime');

            // Create fontawesome finder
            $container
                ->register('ocubom_twig_extra.svg.fontawesome_finder', FontAwesomeFinder::class)
                ->setArguments([
                    new Reference('.ocubom_twig_extra.svg.fontawesome_finder.inner'),
                    new Reference('logger'),
                ]);

            // Create class aliases
            $container->setAlias(FontAwesomeFinder::class, 'ocubom_twig_extra.svg.fontawesome_finder');
        }
    }

    private function loadWebpackEncore(ContainerBuilder $container, array $config): void
    {
        $container->register('ocubom_twig_extra.twig_webpack_encore_extension', WebpackEncoreExtension::class)
            ->setArguments([
                new Reference('webpack_encore.entrypoint_lookup_collection'),
                $config['output_paths'],
            ])
            ->addTag('twig.extension');
    }
}
