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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Caster\ClassStub;

class SvgDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public const TRACE_SUCCESS = 'success';
    public const TRACE_FAILURE = 'failure';
    public const TRACE_WARNING = 'warning';

    public const TRACE_LEVELS = [
        self::TRACE_SUCCESS,
        self::TRACE_WARNING,
        self::TRACE_FAILURE,
    ];

    /** @var array<array-key,SvgTraceableLoader> */
    private array $loaders = [];

    public function addLoader(string $name, SvgTraceableLoader $loader): void
    {
        $this->loaders[$name] = $loader;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        // Noop. Everything is collected live by the traceable loader & cloned as late as possible.
    }

    public function lateCollect(): void
    {
        $data = [
            'svg' => [
                'instances' => [],
            ],
            'loaders' => [
                'instances' => [],
                'counters' => [
                    'success' => 0,
                    'warning' => 0,
                    'failure' => 0,
                ],
            ],
        ];

        foreach ($this->getLogTraces() as $trace) {
            // Initialize loader struct
            $data['loaders']['instances'][$trace['loader_name']] = $data['loaders']['instances'][$trace['loader_name']] ?? [
                'class' => $trace['loader_class'],
                'counters' => [
                    'success' => 0,
                    'warning' => 0,
                    'failure' => 0,
                ],
            ];

            // Update loader counters
            ++$data['loaders']['counters'][$trace['type']];
            ++$data['loaders']['instances'][$trace['loader_name']]['counters'][$trace['type']];

            // Generate a unique key for the search
            $key = sha1($trace['search_ident']);

            $data['svg']['instances'][$key] = $data['svg']['instances'][$key] ?? [
                // Metadata
                'key' => $key,
                'timestamp' => $trace['ts1']->format('Y-m-d\\TH:i:s.uP'),
                'ts0' => $trace['ts0'],
                'ts1' => $trace['ts1'],
                // Search input
                'search_ident' => $trace['search_ident'],
                // Search output
                'value' => $trace['value'],
                'type' => $trace['type'],
                'level' => $trace['level'],
                // Extra
                'traces' => [],
                'counters' => [
                    'success' => 0,
                    'warning' => 0,
                    'failure' => 0,
                ],
            ];

            // Update global data if this trace improves resolution
            if ($trace['level'] < $data['svg']['instances'][$key]['level']) {
                $data['svg']['instances'][$key] = array_merge($data['svg']['instances'][$key], [
                    // Search output
                    'value' => $trace['value'],
                    'type' => $trace['type'],
                ]);
            }

            $traceKey = sha1(serialize([
                $trace['search_ident'],
                $trace['search_options'],
                $trace['loader_name'],
            ]));

            $data['svg']['instances'][$key]['traces'][$traceKey] = array_merge($trace, [
                'key' => $traceKey,
                'timestamp' => $trace['ts1']->format('Y-m-d\\TH:i:s.uP'),
            ]);
        }

        uasort($data['svg']['instances'], function ($x, $y) {
            return strnatcasecmp($x['search_ident'], $y['search_ident'])
                ?: $x['ts1'] <=> $y['ts1']
                ?: strnatcasecmp($x['loader_name'], $y['loader_name']);
        });

        $data['svg']['counters'] = array_reduce(
            $data['svg']['instances'],
            function ($counters, $svg) {
                ++$counters[$svg['type']];

                return $counters;
            },
            [
                'success' => 0,
                'warning' => 0,
                'failure' => 0,
            ]
        );

        $this->data = $this->cloneVar($data);
    }

    private function getLogTraces(): iterable
    {
        $logs = [];

        foreach ($this->loaders as $name => $loader) {
            foreach ($loader->getTraces() as $trace) {
                $trace['search_options'] = $trace['search_options'] ?? [];
                if (is_array($trace['search_options'])) {
                    uksort($trace['search_options'], 'strnatcasecmp');
                }

                $logs[] = array_merge($trace, [
                    // Loader
                    'loader_class' => new ClassStub($loader->getTracedClassName()),
                    'loader_name' => $name,
                    // Search output
                    'type' => $type = $trace['value'] instanceof \Throwable
                        ? self::TRACE_FAILURE
                        : self::TRACE_SUCCESS,
                    'level' => array_search($type, self::TRACE_LEVELS),
                    'value' => $trace['value'] instanceof \Throwable
                        ? $trace['value']
                        : (string) $trace['value'],
                ]);
            }
        }

        usort($logs, function ($x, $y) {
            return $x['ts1'] <=> $y['ts1']
                ?: strnatcasecmp($x['loader_name'], $y['loader_name']);
        });

        foreach ($logs as $trace) {
            yield sha1($trace['search_ident']) => $trace;
        }
    }

    public function reset(): void
    {
        $this->data = [];

        foreach ($this->loaders as $loader) {
            $loader->resetTraces();
        }
    }

    public function getName(): string
    {
        return 'svg';
    }

    public function getSvg(): array
    {
        return iterator_to_array($this->data['svg']['instances']);
    }

    public function getSvgTotalCount(): int
    {
        return count($this->data['svg']['instances']);
    }

    public function getSvgSuccessCount(): int
    {
        return $this->data['svg']['counters']['success'] ?? 0;
    }

    public function getSvgWarningCount(): int
    {
        return $this->data['svg']['counters']['warning'] ?? 0;
    }

    public function getSvgFailureCount(): int
    {
        return $this->data['svg']['counters']['failure'] ?? 0;
    }

    public function getLoaders(): array
    {
        return iterator_to_array(call_user_func(function () {
            foreach ($this->data['loaders']['instances'] as $name => $loader) {
                yield $name => $loader['class'];
            }
        }));
    }

    public function getLoaderTotalCount(string $name = null): int
    {
        return (int) array_sum($this->getLoaderCounters($name));
    }

    public function getLoaderSuccessCount(string $name = null): int
    {
        return $this->getLoaderCounters($name)['success'] ?? 0;
    }

    public function getLoaderWarningCount(string $name = null): int
    {
        return $this->getLoaderCounters($name)['warning'] ?? 0;
    }

    public function getLoaderFailureCount(string $name = null): int
    {
        return $this->getLoaderCounters($name)['failure'] ?? 0;
    }

    public function getLoaderCounters(string $name = null): array
    {
        return iterator_to_array(call_user_func(function () use ($name) {
            $counters = empty($name)
                ? $this->data['loaders']['counters']
                : $this->data['loaders']['instances'][$name]['counters'];

            foreach (array_keys(iterator_to_array($counters)) as $key) {
                yield $key => $counters[$key];
            }
        }));
    }
}
