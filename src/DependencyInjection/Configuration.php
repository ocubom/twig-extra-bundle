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

        $root
            ->beforeNormalization()
                ->always(static function ($v) {
                    // Convert to finders (1.x) configuration
                    $v['finders'] = $v['finders'] ?? [];
                    // Default finder
                    $v['finders']['default'] = $v['finders']['default'] ?? $v['search_path'] ?? [];
                    unset($v['search_path']);
                    // FontAwesome
                    $v['finders']['fontawesome'] = $v['finders']['fontawesome'] ?? $v['fontawesome']['search_path'] ?? [];
                    unset($v['fontawesome']);

                    // Convert to providers (2.x) configuration
                    foreach ($v['finders'] as $key => $val) {
                        if (empty($val)) {
                            continue;
                        }

                        switch ($key) {
                            case 'default':
                                $v['providers']['file_system']['paths'] = $v['providers']['file_system']['paths'] ?? $val;
                                break;

                            case 'fontawesome':
                                $v['providers']['font_awesome']['paths'] = $v['providers']['font_awesome']['paths'] ?? $val;
                                break;

                            default:
                                $v['providers'][$key]['paths'] = $v['providers'][$key]['paths'] ?? $val;
                                break;
                        }
                    }
                    unset($v['finders']);

                    return $v;
                })
            ->end()
            ->validate()
                ->always(function ($v) {
                    // Clean deprecated configuration
                    unset($v['search_path']);
                    unset($v['fontawesome']);
                    unset($v['finders']);

                    // Enable/Disable providers
                    $enabled = 0;
                    foreach (array_keys($v['providers'] ?? []) as $key) {
                        $v['providers'][$key]['paths'] = array_filter($v['providers'][$key]['paths'] ?? []);
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

        // Add providers (2.x) configuration
        $parent = $root
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('providers')
                    ->info('SVG providers.')
                    ->addDefaultsIfNotSet();

        foreach ($providers as $key => $val) {
            $provider = $parent
                ->children()
                    ->arrayNode($key);

            $children = $provider
                    ->info(sprintf('%s provider.', $val['name']))
                    ->canBeDisabled()
                    ->children();

            $enabled = $provider->find('enabled');
            assert($enabled instanceof BooleanNodeDefinition);
            $enabled->info('Enable or disable this provider.');

            // Provider paths
            $provider->fixXmlConfig('path');
            $children
                ->arrayNode('paths')
                    ->info(sprintf('The paths where the %s files will be searched for.', $val['name']))
                    // ->example(sprintf('["%s"]', implode('", "', $val['paths'])))
                    ->defaultValue($val['paths'])
                    ->prototype('scalar')->end()
                ->end();

            // Extra configuration
            if (is_callable($val['loader'] ?? null)) {
                $val['loader']($children->arrayNode('loader')
                    ->info(sprintf('Loader configuration options for %s', $val['name']))
                    ->addDefaultsIfNotSet()
                    ->children()
                );
            }

            if (is_callable($val['runtime'] ?? null)) {
                $val['runtime']($children->arrayNode('runtime')
                    ->info(sprintf('Runtime configuration options for %s', $val['name']))
                    ->addDefaultsIfNotSet()
                    ->children()
                );
            }
        }

        // Add finders (1.x) configuration
        $root
            ->fixXmlConfig('finder')
            ->children()
                ->arrayNode('finders')
                    ->setDeprecated(
                        'ocubom/twig-extra-bundle',
                        '1.3',
                        'The "%node%" option is deprecated. Use "providers" instead.'
                    )
                    ->info('The paths where SVG files will be searched for.')
                    ->children()
                        ->arrayNode('default')
                            ->info('The default paths where the SVG files will be searched for.')
                            ->example(sprintf('["%s"]', implode('", "', $providers['file_system']['paths'])))
                            // ->defaultValue($providers['file_system']['paths'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('fontawesome')
                            ->info('The paths where the FontAwesome files will be searched for.')
                            ->example(sprintf('["%s"]', implode('", "', $providers['font_awesome']['paths'])))
                            // ->defaultValue($providers['font_awesome']['paths'])
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('search_path')
                    ->setDeprecated(
                        'ocubom/twig-extra-bundle',
                        '1.2',
                        'The "%node%" option is deprecated. Use "providers.filesystem" instead.'
                    )
                    ->info('The paths where the SVG files will be searched for.')
                    ->example(sprintf('["%s"]', implode('", "', $providers['file_system']['paths'])))
                    // ->defaultValue($providers['file_system']['paths'])
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('fontawesome')
                    ->setDeprecated(
                        'ocubom/twig-extra-bundle',
                        '1.2',
                        'The "%node%" option is deprecated. Use "providers.fontawesome" instead.'
                    )
                    ->info('Configuration for FontAwesome.')
                    ->children()
                        ->arrayNode('search_path')
                            ->info('The paths where the FontAwesome files will be searched for.')
                            ->example(sprintf('["%s"]', implode('", "', $providers['font_awesome']['paths'])))
                            // ->defaultValue($providers['font_awesome']['paths'])
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addWebpackEncoreSection(ArrayNodeDefinition $root): void
    {
        $defaultPaths = [
            '%kernel.project_dir%/public/build',
        ];

        $root
            ->children()
                ->arrayNode('output_paths')
                    ->info('Paths where Symfony Encore will generate its output.')
                    // ->example(sprintf('["%s"]', implode('", "', $defaultPaths)))
                    ->defaultValue($defaultPaths)
                    ->prototype('scalar')->end()
            ->end();
    }
}
