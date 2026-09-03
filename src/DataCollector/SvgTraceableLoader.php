<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\DataCollector;

use Ocubom\Twig\Extension\Svg\Loader\LoaderInterface;
use Ocubom\Twig\Extension\Svg\Svg;
use Symfony\Component\Stopwatch\Stopwatch;

class SvgTraceableLoader implements LoaderInterface
{
    private LoaderInterface $inner;
    private ?Stopwatch $watch;

    /**
     * @var \ReflectionClass<LoaderInterface>
     */
    private \ReflectionClass $class;

    /**
     * @var list<array{
     *     ts0: \DateTimeImmutable,
     *     ts1: \DateTimeImmutable,
     *     loader_class: string,
     *     search_ident: string,
     *     search_options: ?iterable<mixed>,
     *     value: Svg|\Throwable|null
     * }>
     */
    private array $traces = [];

    public function __construct(LoaderInterface $inner, ?Stopwatch $watch = null)
    {
        $this->inner = $inner;
        $this->watch = $watch;
        $this->class = new \ReflectionClass($this->inner);
    }

    public function resolve(string $ident, ?iterable $options = null): Svg
    {
        $this->traces[$idx = count($this->traces)] = [
            // Metadata
            'ts0' => $now = new \DateTimeImmutable(),
            'ts1' => $now,
            // Loader
            'loader_class' => $loader = $this->class->getShortName(),
            // Search input
            'search_ident' => $ident,
            'search_options' => $options,
            // Search output
            'value' => null,
        ];

        if ($this->watch instanceof Stopwatch) {
            $this->watch->start($loader, 'ocubom.svg_loader');
        }

        try {
            return $this->traces[$idx]['value'] = $this->inner->resolve($ident, $options);
        } catch (\Throwable $err) {
            // Register error as "returned" value
            $this->traces[$idx]['value'] = $err;

            throw $err;
        } finally {
            // Update finish timestamp
            $this->traces[$idx]['ts1'] = new \DateTimeImmutable();

            if ($this->watch instanceof Stopwatch) {
                $this->watch->stop($loader);
            }
        }
    }

    /**
     * @return list<array{
     *     ts0: \DateTimeImmutable,
     *     ts1: \DateTimeImmutable,
     *     loader_class: string,
     *     search_ident: string,
     *     search_options: ?iterable<mixed>,
     *     value: Svg|\Throwable|null
     * }>
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    public function resetTraces(): void
    {
        $this->traces = [];
    }

    public function getTracedClassName(): string
    {
        return $this->class->getName();
    }
}
