<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\Tests\DependencyInjection;

use Ocubom\Twig\Extension\Svg\Loader\ChainLoader;
use Ocubom\Twig\Extension\Svg\Loader\LoaderInterface;
use Ocubom\Twig\Extension\Svg\Provider\FileSystem\FileSystemLoader;
use Ocubom\Twig\Extension\Svg\Provider\FontAwesome\FontAwesomeLoader;
use Ocubom\Twig\Extension\Svg\Provider\Iconify\IconifyLoader;
use Ocubom\Twig\Extension\Svg\Provider\Iconify\IconifyRuntime;
use Ocubom\TwigExtraBundle\DependencyInjection\OcubomTwigExtraExtension;
use Ocubom\TwigExtraBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class OcubomTwigExtraExtensionTest extends TestCase
{
    private $kernel;

    /**
     * @var Container
     */
    private $container;

    public static function assertSaneContainer(Container $container)
    {
        $removedIds = $container->getRemovedIds();
        $errors = [];
        foreach ($container->getServiceIds() as $id) {
            if (isset($removedIds[$id])) {
                continue;
            }
            try {
                $container->get($id);
            } catch (\Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        self::assertSame([], $errors);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = $this->createMock(KernelInterface::class);

        $profiler = $this->createMock(Profiler::class);
        $profilerStorage = $this->createMock(ProfilerStorageInterface::class);
        // $router = $this->createMock(RouterInterface::class);

        $this->container = new ContainerBuilder();
        $this->container->register('data_collector.dump', DumpDataCollector::class)->setPublic(true);
        $this->container->register('error_handler.error_renderer.html', HtmlErrorRenderer::class)->setPublic(true);
        $this->container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        $this->container->register('twig', Environment::class)->setPublic(true);
        $this->container->register('twig_loader', ArrayLoader::class)->addArgument([])->setPublic(true);
        $this->container->register('twig', Environment::class)->addArgument(new Reference('twig_loader'))->setPublic(true);
        $this->container->setParameter('kernel.bundles', []);
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.build_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.charset', 'UTF-8');
        $this->container->setParameter('debug.file_link_format', null);
        $this->container->setParameter('profiler.class', [Profiler::class]);
        $this->container->register('profiler', get_class($profiler))
            ->setPublic(true)
            ->addArgument(new Definition(get_class($profilerStorage)));
        $this->container->setParameter('data_collector.templates', []);
        $this->container->set('kernel', $this->kernel);
        $this->container->addCompilerPass(new RegisterListenersPass());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->container = null;
        $this->kernel = null;
    }

    public function testDefaultConfig()
    {
        $extension = new OcubomTwigExtraExtension();
        $extension->load([[]], $this->container);

        $this->assertTrue($this->container->has('ocubom_twig_extra.twig_html_extension'));
        $this->assertTrue($this->container->has('ocubom_twig_extra.twig_html_attributes_runtime'));
        $this->assertTrue($this->container->has('ocubom_twig_extra.twig_html_compress_runtime'));

        $this->assertTrue($this->container->has('ocubom_twig_extra.twig_svg_extension'));
        $this->assertTrue($this->container->has('ocubom_twig_extra.twig_svg_runtime'));
        if (class_exists(IconifyRuntime::class)) {
            // v2.x
            $this->assertTrue($this->container->has('ocubom_twig_extra.twig_svg_font_awesome_runtime'));
            $this->assertTrue($this->container->has('ocubom_twig_extra.twig_svg_iconify_runtime'));
        } else {
            // v1.x
            $this->assertTrue($this->container->has('ocubom_twig_extra.twig_fontawesome_runtime'));
        }

        $this->assertTrue($this->container->has('ocubom_twig_extra.twig_webpack_encore_extension'));

        if (interface_exists(LoaderInterface::class)) {
            $loaders = [
                'ocubom_twig_extra.svg_loader' => ChainLoader::class,
                'ocubom_twig_extra.svg_loader.file_system' => FileSystemLoader::class,
                'ocubom_twig_extra.svg_loader.font_awesome' => FontAwesomeLoader::class,
                'ocubom_twig_extra.svg_loader.iconify' => IconifyLoader::class,
            ];
            foreach ($loaders as $ident => $class) {
                $this->assertTrue($this->container->has($ident));

                $loader = $this->container->get($ident);
                $this->assertInstanceOf($class, $loader);
            }
        }

        self::assertSaneContainer($this->getCompiledContainer());
    }

    private function getCompiledContainer()
    {
        if ($this->container->has('web_profiler.debug_toolbar')) {
            $this->container->getDefinition('web_profiler.debug_toolbar')->setPublic(true);
        }
        $this->container->compile();
        $this->container->set('kernel', $this->kernel);

        return $this->container;
    }
}
