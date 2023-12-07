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

use Iconify\IconsJSON\Finder as IconifyFinder;
use Ocubom\TwigExtraBundle\Extensions;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('ocubom_twig_extra');
        $root = $builder->getRootNode();

        // Register available extensions
        foreach (Extensions::getClasses() as $name => $class) {
            $call = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
            $this->{'add'.$call.'Section'}(
                $this->createSectionNode($root, $name, class_exists($class))
            );
        }

        // Headers listener included on this bundle
        $this->addHttpHeaderSection($root);

        return $builder;
    }

    private function createSectionNode(
        ArrayNodeDefinition $root,
        string $name,
        bool $canBeDisabled = true
    ): ArrayNodeDefinition {
        $node = $root->children()->arrayNode($name);
        assert($node instanceof ArrayNodeDefinition);

        $node->info(sprintf('Twig %s Extension', ucfirst($name)));
        $node->{$canBeDisabled ? 'canBeDisabled' : 'canBeEnabled'}();

        $enabled = $node->find('enabled');
        assert($enabled instanceof BooleanNodeDefinition);
        $enabled->info('Enable or disable this extension');

        return $node;
    }

    private function addHttpHeaderSection(ArrayNodeDefinition $root): void
    {
        $root
            ->fixXmlConfig('http_header')
            ->children()
                ->arrayNode('http_headers')
                    ->info(implode("\n", [
                        'HTTP headers that must be set.',
                        'The listener will only be registered if at least one rule is enabled.',
                    ]))
                    ->prototype('array')
                        ->treatFalseLike(['enabled' => false])
                        ->treatTrueLike(['enabled' => true])
                        ->treatNullLike(['enabled' => true])
                        ->children()
                            ->booleanNode('enabled')
                                ->info('Enable or disable this rule')
                                ->defaultTrue()
                            ->end()
                            ->scalarNode('name')
                                ->info('The header name to be added.')
                                ->example('X-UA-Compatible')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('pattern')
                                ->info('A regular expression to extract the header value.')
                                ->example('@@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('value')
                                ->info('The format of the value (printf processed using the matched value).')
                                ->example('%2$s')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('replace')
                                ->info('The text that replaces the match in the original (printf processed using the matched value).')
                                ->defaultValue('%s')
                            ->end()
                            ->arrayNode('formats')
                                ->info('The response formats when this replacement must be done.')
                                ->example('["text/html"]')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addHtmlSection(ArrayNodeDefinition $root): void
    {
        $children = $root
            ->children()
                ->arrayNode('compression')
                    ->info('Compress HTML output')
                    ->addDefaultsIfNotSet()
                    ->children();

        $children
            ->booleanNode('force')
                ->info('Force compression')
                ->defaultFalse()
            ->end();

        $children
            ->enumNode('level')
                ->info('The level of compression to use')
                ->defaultValue('smallest')
                ->values([
                    'none',
                    'fastest',
                    'normal',
                    'smallest',
                ])
            ->end();
    }

    private function addSvgSection(ArrayNodeDefinition $root): void
    {
        $providers = [
            'file_system' => [
                'name' => 'Local File System',
                'paths' => [
                    '%kernel.project_dir%/assets',
                    '%kernel.project_dir%/node_modules',
                ],
            ],
            'font_awesome' => [
                'name' => 'FontAwesome',
                'paths' => [
                    '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-pro/svgs',
                    '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-free/svgs',
                    '%kernel.project_dir%/vendor/fortawesome/font-awesome/svgs/',
                ],
            ],
            'iconify' => [
                'name' => 'Iconify',
                'paths' => [
                    '%kernel.project_dir%/node_modules/@iconify-json/',
                    '%kernel.project_dir%/node_modules/@iconify/json/',
                    '%kernel.project_dir%/vendor/iconify/json/',
                    class_exists(IconifyFinder::class) ? IconifyFinder::rootDir() : '',
                ],
                'runtime' => function (NodeBuilder $parent): void {
                    $parent
                        ->arrayNode('svg_framework')
                            ->info('Enable SVG Framework Server Side Rendering on classes (empty to disable).')
                            ->defaultValue(['iconify', 'iconify-inline'])
                            ->prototype('scalar')->end()
                        ->end();

                    $parent
                        ->arrayNode('web_component')
                            ->info('Enable Web Component Server Side Rendering on tags (empty to disable).')
                            ->defaultValue(['icon', 'iconify-icon'])
                            ->prototype('scalar')->end()
                        ->end();
                },
                'loader' => function (NodeBuilder $parent): void {
                    $parent
                        ->scalarNode('cache_dir')
                            ->info('Enable cache on this path (empty to disable).')
                            ->defaultValue('%kernel.cache_dir%/iconify')
                        ->end();
                },
            ],
        ];

        // Register providers configuration
        $parent = $root
            ->fixXmlConfig('provider')
            ->children()
            ->arrayNode('providers')
            ->info('SVG providers.')
            ->addDefaultsIfNotSet();

        foreach ($providers as $key => $val) {
            $provider = $parent->children()->arrayNode($key);

            $children = $provider
                ->info(sprintf('%s provider.', $val['name']))
                ->canBeDisabled()
                ->children();

            $enabled = $provider->find('enabled');
            assert($enabled instanceof BooleanNodeDefinition);
            $enabled->info(sprintf('Enable or disable %s provider.', $val['name']));

            // Provider paths
            $provider->fixXmlConfig('path');
            $children
                ->arrayNode('paths')
                    ->info(sprintf('The paths where the %s provider will search files.', $val['name']))
                    ->defaultValue($val['paths'])
                    ->prototype('scalar')->end()
                ->end();

            // Extra configuration
            if (is_callable($val['loader'] ?? null)) {
                $val['loader']($children->arrayNode('loader')
                    ->info(sprintf('%s custom loader options', $val['name']))
                    ->addDefaultsIfNotSet()
                    ->children()
                );
            }

            if (is_callable($val['runtime'] ?? null)) {
                $val['runtime']($children->arrayNode('runtime')
                    ->info(sprintf('%s custom runtime configuration', $val['name']))
                    ->addDefaultsIfNotSet()
                    ->children()
                );
            }
        }

        // Enable/disable providers and section on validation
        $root
            ->validate()
                ->always(function ($v) {
                    // Enable/Disable providers
                    $enabled = 0;
                    foreach (array_keys($v['providers'] ?? []) as $key) {
                        // Clean provider path
                        $v['providers'][$key]['paths'] = array_filter($v['providers'][$key]['paths'] ?? []);
                        // Enabled if some path is set
                        $v['providers'][$key]['enabled'] = $v['providers'][$key]['enabled'] && count($v['providers'][$key]['paths']) > 0;

                        if ($v['providers'][$key]['enabled']) {
                            ++$enabled;
                        }
                    }

                    // Disable if no provider are enabled
                    $v['enabled'] = $v['enabled'] && $enabled > 0;

                    return $v;
                })
            ->end();
    }

    private function addWebpackEncoreSection(ArrayNodeDefinition $root): void
    {
        $root
            ->children()
                ->arrayNode('output_paths')
                    ->info('Paths where Symfony Encore will generate its output.')
                    ->defaultValue([
                        '%kernel.project_dir%/public/build',
                    ])
                    ->prototype('scalar')->end()
            ->end();
    }
}
