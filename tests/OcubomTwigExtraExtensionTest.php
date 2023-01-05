<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\Tests;

use Ocubom\Twig\Extension\HtmlExtension;
use Ocubom\Twig\Extension\SvgExtension;
use Ocubom\TwigExtraBundle\DependencyInjection\OcubomTwigExtraExtension;
use Ocubom\TwigExtraBundle\Listener\AddHttpHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class OcubomTwigExtraExtensionTest extends TestCase
{
    /** @dataProvider provideConfiguration */
    public function testConfiguration($builder, $expected)
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
        ]));
        (new ClosureLoader($container))->load($builder);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        foreach ($expected as $name => $class) {
            if (null === $class) {
                $this->assertFalse($container->hasDefinition($name));
            } else {
                $this->assertEquals($class, $container->getDefinition($name)->getClass());
            }
        }
    }

    public function provideConfiguration()
    {
        yield 'default' => [
            function (ContainerBuilder $container) {
                $container->registerExtension(new OcubomTwigExtraExtension());
                $container->loadFromExtension('ocubom_twig_extra', [
                    'html' => null,
                    'svg' => null,
                    'http_headers' => null,
                ]);
            },
            [
                'ocubom_twig_extra.listener.http_headers' => null,
                'ocubom_twig_extra.twig_html_extension' => HtmlExtension::class,
                'ocubom_twig_extra.twig_svg_extension' => null,
            ],
        ];

        yield 'html disabled' => [
            function (ContainerBuilder $container) {
                $container->registerExtension(new OcubomTwigExtraExtension());
                $container->loadFromExtension('ocubom_twig_extra', [
                    'html' => ['enabled' => false],
                    'svg' => null,
                    'http_headers' => null,
                ]);
            },
            [
                'ocubom_twig_extra.http_headers_listener' => null,
                'ocubom_twig_extra.twig_html_extension' => null,
                'ocubom_twig_extra.twig_svg_extension' => null,
            ],
        ];

        yield 'http_headers' => [
            function (ContainerBuilder $container) {
                $container->registerExtension(new OcubomTwigExtraExtension());
                $container->loadFromExtension('ocubom_twig_extra', [
                    'html' => null,
                    'svg' => null,
                    'http_headers' => array_values(AddHttpHeadersTest::$rules),
                ]);
            },
            [
                'ocubom_twig_extra.http_headers_listener' => AddHttpHeadersListener::class,
                'ocubom_twig_extra.twig_html_extension' => HtmlExtension::class,
                'ocubom_twig_extra.twig_svg_extension' => null,
            ],
        ];

        yield 'svg' => [
            function (ContainerBuilder $container) {
                $container->registerExtension(new OcubomTwigExtraExtension());
                $container->loadFromExtension('ocubom_twig_extra', [
                    'html' => null,
                    'svg' => [
                        'finders' => [
                            'default' => ['ruta'],
                            'fontawesome' => ['ruta'],
                        ],
                    ],
                    'http_headers' => null,
                ]);
            },
            [
                'ocubom_twig_extra.listener.http_headers' => null,
                'ocubom_twig_extra.twig_html_extension' => HtmlExtension::class,
                'ocubom_twig_extra.twig_svg_extension' => SvgExtension::class,
            ],
        ];
    }
}
