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
    private \ReflectionClass $class;
    private array $traces = [];

    public function __construct(LoaderInterface $inner, Stopwatch $watch = null)
    {
        $this->inner = $inner;
        $this->class = new \ReflectionClass($this->inner);

        $this->watch = $watch;
    }

    public function resolve(string $ident, iterable $options = null): Svg
    {
        try {
            if ($this->watch instanceof Stopwatch) {
                $this->watch->start($this->class->getShortName(), 'ocubom.svg_loader');
            }

            $this->traces[$idx = count($this->traces)] = [
                // Metadata
                'ts0' => \DateTimeImmutable::createFromFormat('0.u00 U', microtime()),
                'ts1' => \DateTimeImmutable::createFromFormat('0.u00 U', microtime()),
                // Loader
                'loader_class' => $this->class->getName(),
                // Search input
                'search_ident' => $ident,
                'search_options' => $options,
                // Search output
                'value' => null,
            ];

            return $this->traces[$idx]['value'] = $this->inner->resolve($ident, $options);
        } catch (\Throwable $err) {
            // Register error as "returned" value
            $idx = count($this->traces) - 1;
            $this->traces[$idx]['value'] = $err;

            throw $err;
        } finally {
            // Update finish timestamp
            $idx = count($this->traces) - 1;
            $this->traces[$idx]['ts1'] = \DateTimeImmutable::createFromFormat('0.u00 U', microtime());

            if ($this->watch instanceof Stopwatch) {
                $this->watch->stop($this->class->getShortName());
            }
        }
    }

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
