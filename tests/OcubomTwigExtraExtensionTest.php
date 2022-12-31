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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class OcubomTwigExtraExtensionTest extends TestCase
{
    /** @dataProvider provideConfiguration */
    public function testConfiguration($buildContainer, $expected)
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
        ]));
        $container->registerExtension(new OcubomTwigExtraExtension());
        $buildContainer($container);
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
                $container->loadFromExtension('ocubom_twig_extra');
            },
            [
                'ocubom_twig_extra.listener.http_headers' => null,
                'ocubom_twig_extra.extension.html' => HtmlExtension::class,
                'ocubom_twig_extra.extension.svg' => SvgExtension::class,
            ],
        ];

        yield 'with headers' => [
            function (ContainerBuilder $container) {
                $container->registerExtension(new OcubomTwigExtraExtension());
                $container->loadFromExtension('ocubom_twig_extra', [
                    'http_headers' => array_values(AddHttpHeadersTest::$rules),
                ]);
            },
            [
                'ocubom_twig_extra.listener.http_headers' => AddHttpHeadersListener::class,
                'ocubom_twig_extra.extension.html' => HtmlExtension::class,
                'ocubom_twig_extra.extension.svg' => SvgExtension::class,
            ],
        ];
    }
}
