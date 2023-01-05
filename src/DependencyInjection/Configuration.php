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

use Ocubom\TwigExtraBundle\Extensions;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
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
        $node = $root
            ->children()
                ->arrayNode($name)
                    ->{$canBeDisabled ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->info(sprintf(
                        'Twig %s Extension',
                        ucfirst($name)
                    ));
        assert($node instanceof ArrayNodeDefinition);

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
                                // ->example('')
                                ->defaultValue('%s')
                            ->end()
                            ->arrayNode('formats')
                                ->info('The response formats when this replacement must be done.')
                                ->example('["text/html"]')
                                // ->addDefaultChildrenIfNoneSet()
                                ->prototype('scalar')
                                    // ->defaultValue('text/html')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addHtmlSection(ArrayNodeDefinition $root): void
    {
        $root
            ->children()
                ->arrayNode('compression')
                    ->info('Compress HTML output')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('force')
                            ->info('Force compression')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('level')
                            ->info('The level of compression to use')
                            ->defaultValue('smallest')
                            ->values([
                                'none',
                                'fastest',
                                'normal',
                                'smallest',
                            ])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addSvgSection(ArrayNodeDefinition $root): void
    {
        $examples = [
            'default' => [
                '%kernel.project_dir%/assets',
                '%kernel.project_dir%/node_modules',
            ],
            'fontawesome' => [
                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-pro/svgs',
                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-free/svgs',
                '%kernel.project_dir%/vendor/fortawesome/font-awesome/svgs/',
            ],
        ];

        $finderKeys = array_keys($examples);

        $root
            ->beforeNormalization()
                ->always(static function ($v) use ($finderKeys) {
                    // Convert deprecated configuration
                    $v['finders'] = $v['finders'] ?? [];
                    foreach ($finderKeys as $key) {
                        switch ($key) {
                            case 'default':
                                $v['finders'][$key] = $v['finders'][$key] ?? $v['search_path'] ?? [];
                                unset($v['search_path']);
                                break;

                            case 'fontawesome':
                                $v['finders'][$key] = $v['finders'][$key] ?? $v['fontawesome']['search_path'] ?? [];
                                unset($v['fontawesome']);
                                break;
                        }
                    }

                    return $v;
                })
            ->end()
            ->validate()
                ->always(function ($v) {
                    // Clean deprecated configuration
                    unset($v['search_path']);

                    // Disable if no finder are registered
                    $v['finders'] = array_filter($v['finders'] ?? []);
                    $v['enabled'] = (0 !== count($v['finders']));

                    return $v;
                })
            ->end()
            ->fixXmlConfig('finder')
            ->children()
                ->arrayNode('finders')
                    ->info('The paths where SVG files will be searched for.')
                    ->children()
                        ->arrayNode('default')
                            ->info('The default paths where the SVG files will be searched for.')
                            ->example(sprintf('["%s"]', implode('", "', $examples['default'])))
                            ->defaultValue($examples['default'])
                            ->prototype('scalar')
                            ->end()
                        ->end()
                        ->arrayNode('fontawesome')
                            ->info('The paths where the FontAwesome files will be searched for.')
                            ->example(sprintf('["%s"]', implode('", "', $examples['fontawesome'])))
                            ->defaultValue($examples['fontawesome'])
                            ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('search_path')
                    ->setDeprecated(
                        'ocubom/twig-extra-bundle',
                        '1.2',
                        'The "%node%" option is deprecated. Use "finder.svg" instead.'
                    )
                    ->info('The paths where the SVG files will be searched for.')
                    ->example(sprintf('["%s"]', implode('", "', $examples['default'])))
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('fontawesome')
                    ->setDeprecated(
                        'ocubom/twig-extra-bundle',
                        '1.2',
                        'The "%node%" option is deprecated. Use "finder.fontawesome" instead.'
                    )
                    ->info('Configuration for FontAwesome.')
                    ->children()
                        ->arrayNode('search_path')
                            ->info('The paths where the FontAwesome files will be searched for.')
                            ->example(sprintf('["%s"]', implode('", "', [
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-pro/svgs',
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-free/svgs',
                            ])))
                            ->defaultValue([
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-pro/svgs',
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-free/svgs',
                            ])
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addWebpackEncoreSection(ArrayNodeDefinition $root): void
    {
        $root
            ->children()
                ->arrayNode('output_paths')
                    ->info('Paths where Symfony Encore will generate its output.')
                    ->example(sprintf('["%s"]', implode('", "', [
                        '%kernel.project_dir%/public/build',
                    ])))
                    ->defaultValue([
                        '%kernel.project_dir%/public/build',
                    ])
                    ->prototype('scalar')
                ->end()
            ->end();
    }
}
