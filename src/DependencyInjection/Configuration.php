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
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
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
            $this->{'addTwig'.ucfirst($name).'ExtensionSection'}(
                $this->createSectionNode($root, $name, class_exists($class))
            );
        }

        // Headers listener included on this bundle
        $this->addHttpHeaderSection($root);

        return $builder;
    }

    private function createSectionNode(
        NodeDefinition $root,
        string $name,
        bool $canBeDisabled = true
    ): ArrayNodeDefinition {
        return $root
            ->children()
                ->arrayNode($name)
                    ->info(sprintf(
                        'Twig %s Extension',
                        ucfirst($name)
                    ))
                    ->{$canBeDisabled ? 'canBeDisabled' : 'canBeEnabled'}();
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
                                ->info('Apply this rule?')
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

    private function addTwigHtmlExtensionSection(ArrayNodeDefinition $root): void
    {
        $root
            ->children()
                ->arrayNode('compression')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('force')
                            ->info('Force compression?')
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

    private function addTwigSvgExtensionSection(ArrayNodeDefinition $root): void
    {
        $root
            ->children()
                ->arrayNode('search_path')
                    ->info('The paths where the SVG files will be searched for.')
                    ->example(sprintf('["%s"]', implode('", "', [
                        '%kernel.project_dir%/assets',
                        '%kernel.project_dir%/node_modules',
                    ])))
                    ->defaultValue([
                        '%kernel.project_dir%/assets',
                        '%kernel.project_dir%/node_modules',
                    ])
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('fontawesome')
                    ->info('Configuration for FontAwesome.')
                    ->children()
                        ->arrayNode('search_path')
                            ->info(implode("\n", [
                                'The paths where the FontAwesome files will be searched for.',
                                'If not set the global search_path will be used.',
                            ]))
                            ->example(sprintf('["%s"]', implode('", "', [
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-pro/svgs',
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-free/svgs',
                            ])))
                            ->defaultValue([
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-pro/svgs',
                                '%kernel.project_dir%/node_modules/@fortawesome/fontawesome-free/svgs',
                            ])
                            ->prototype('scalar')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
