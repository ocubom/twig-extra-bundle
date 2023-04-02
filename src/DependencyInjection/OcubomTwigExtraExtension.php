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
use Ocubom\Twig\Extension\Svg\Loader\ChainLoader;
use Ocubom\Twig\Extension\Svg\Loader\LoaderInterface;
use Ocubom\Twig\Extension\Svg\Util\PathCollection;
use Ocubom\Twig\Extension\SvgExtension;
use Ocubom\Twig\Extension\SvgRuntime;
use Ocubom\TwigExtraBundle\Extensions;
use Ocubom\TwigExtraBundle\Listener\AddHttpHeadersListener;
use Ocubom\TwigExtraBundle\Twig\WebpackEncoreExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

use function BenTools\IterableFunctions\iterable_to_array;
use function Ocubom\Math\base_convert;

class OcubomTwigExtraExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        assert($configuration instanceof ConfigurationInterface);
        $config = $this->processConfiguration($configuration, $configs);

        // Load enabled extensions
        foreach (array_keys(Extensions::getClasses()) as $name) {
            if ($this->isConfigEnabled($container, $config[$name])) {
                $call = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));

                $this->{'load'.$call}($container, $config[$name]);
            }
        }

        // Load headers listener
        $this->loadHttpHeaders($container, $config['http_headers']);
    }

    private function loadHttpHeaders(ContainerBuilder $container, array $config): void
    {
        // Filter enabled header rules
        $headers = array_filter($config, function (array $header): bool {
            return (bool) $header['enabled'];
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

    /** @psalm-suppress UndefinedClass */
    private function loadSvg(ContainerBuilder $container, array $config): void
    {
        if (empty($config['providers'])) {
            return;
        }

        // Register the extension
        $container->register('ocubom_twig_extra.twig_svg_extension', SvgExtension::class)
            ->addTag('twig.extension');

        switch (true) {
            case interface_exists(LoaderInterface::class):
                $this->loadSvgLoaders($container, $config);
                break;

            case interface_exists(FinderInterface::class):
                $this->loadSvgFinders($container, $config);
                break;
        }
    }

    private function loadSvgLoaders(ContainerBuilder $container, array $config): void
    {
        // Register global loader
        $container->register('ocubom_twig_extra.svg_loader', ChainLoader::class)
            ->setArguments([
                new TaggedIteratorArgument('ocubom_twig_extra.svg_loader'),
            ]);

        // Register global runtime
        $container->register('ocubom_twig_extra.twig_svg_runtime', SvgRuntime::class)
            ->setArguments([
                new Reference('ocubom_twig_extra.svg_loader'),
            ])
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('twig.runtime');

        // Register individual providers
        foreach ($config['providers'] as $name => $provider) {
            if (!$provider['enabled']) {
                continue; // @codeCoverageIgnore
            }

            $case = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));

            // Loader
            $loaderClass = "Ocubom\\Twig\\Extension\\Svg\\Provider\\{$case}\\{$case}Loader";
            $loaderIdent = "ocubom_twig_extra.svg_loader.{$name}";
            if (class_exists($loaderClass)) {
                // $loaderClass = (new \ReflectionClass($loaderClass))->getName();

                // Register the path collection (when necessary)
                $pathsIdent = base_convert(sha1(serialize($provider['paths'])), 16, 62);
                $pathsIdent = ".ocubom_twig_extra.svg_path_collection.{$pathsIdent}";
                if (!$container->has($pathsIdent)) {
                    $container->register($pathsIdent, PathCollection::class)
                        ->setArguments($provider['paths'])
                        ->setPublic(false);
                }

                // Register loader
                $container->register($loaderIdent, $loaderClass)
                    ->setArguments(iterable_to_array(call_user_func(function () use ($provider, $pathsIdent) {
                        yield new Reference($pathsIdent);

                        // Pass custom loader options
                        if (isset($provider['loader'])) {
                            yield $provider['loader'];
                        }
                    })))
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                    ->addTag('ocubom_twig_extra.svg_loader');
            }

            // Runtime
            $runtimeClass = "Ocubom\\Twig\\Extension\\Svg\\Provider\\{$case}\\{$case}Runtime";
            $runtimeIdent = "ocubom_twig_extra.twig_svg_{$name}_runtime";
            if ($container->has($loaderIdent) && class_exists($runtimeClass)) {
                // Register runtime
                $container
                    ->register($runtimeIdent, $runtimeClass)
                    ->setArguments(iterable_to_array(call_user_func(function () use ($provider, $loaderIdent) {
                        yield new Reference($loaderIdent);

                        // Pass custom runtime options
                        if (isset($provider['runtime'])) {
                            yield $provider['runtime'];
                        }
                    })))
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                    ->addTag('twig.runtime');

                // Register the extension
                if (!$container->has('ocubom_twig_extra.twig_svg_extension')) {
                    $container->register('ocubom_twig_extra.twig_svg_extension', SvgExtension::class)
                        ->addTag('twig.extension');
                }
            }
        }
    }

    /** @psalm-suppress UndefinedClass */
    private function loadSvgFinders(ContainerBuilder $container, array $config): void
    {
        foreach ($config['providers'] as $name => $provider) {
            if (!$provider['enabled']) {
                continue;
            }

            // Path colletion
            $pathsIdent = sha1(serialize($provider['paths']));
            $pathsIdent = ".ocubom_twig_extra.svg.finder.{$pathsIdent}";
            if (!$container->has($pathsIdent)) {
                $container->register($pathsIdent, Finder::class)
                    ->setArguments($provider['paths'])
                    ->setPublic(false)
                    ->addTag('ocubom_twig_extra.svg_finder');
            }

            // Create a hidden alias
            $container->setAlias(".ocubom_twig_extra.svg.{$name}_finder.inner", $pathsIdent);
        }

        // Register default runtime
        if ($container->has('.ocubom_twig_extra.svg.file_system_finder.inner')) {
            // Register runtime
            $container->register('ocubom_twig_extra.twig_svg_runtime', SvgRuntime::class)
                ->setArguments([
                    new Reference('.ocubom_twig_extra.svg.file_system_finder.inner'),
                ])
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('twig.runtime');

            // Create default finder (just an alias)
            $container->setAlias('ocubom_twig_extra.svg.default_finder', '.ocubom_twig_extra.svg.file_system_finder.inner');

            // Create class aliases
            // $container->setAlias(FinderInterface::class, 'ocubom_twig_extra.svg.default_finder');
        }

        // Register fontawesome runtime
        if ($container->has('.ocubom_twig_extra.svg.font_awesome_finder.inner')) {
            // Register runtime
            $container->register('ocubom_twig_extra.twig_fontawesome_runtime', FontAwesomeRuntime::class)
                ->setArguments([
                    new Reference('ocubom_twig_extra.svg.fontawesome_finder'),
                ])
                ->addTag('twig.runtime');

            // Create fontawesome finder
            $container->register('ocubom_twig_extra.svg.fontawesome_finder', FontAwesomeFinder::class)
                ->setArguments([
                    new Reference('.ocubom_twig_extra.svg.font_awesome_finder.inner'),
                ]);

            // Create class aliases
            // $container->setAlias(FontAwesomeFinder::class, 'ocubom_twig_extra.svg.fontawesome_finder');
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
