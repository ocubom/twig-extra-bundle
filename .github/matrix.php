<?php

declare(strict_types=1);

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MatrixGenerator implements IteratorAggregate
{
    // The base matrix
    private const DIMENSIONS = [
        'os' => [
            'ubuntu-latest',
            'macos-latest',
            'windows-latest',
        ],
        'php' => [
            '8.5',
            '8.4',
            '8.3',
            '8.2',
            '8.1',
            '8.0',
            '7.4',
        ],
        'symfony' => [
            '8.1',
            '8.0',
            '7.4',
            '6.4',
            '5.4',
        ],
    ];

    // Rules to drop invalid or unsupported combinations
    private const CONSTRAINTS = [
        'symfony' => [
            '8.1' => ['php' => '>=8.4'],
            '8.0' => ['php' => '>=8.4'],
            '7.4' => ['php' => '>=8.2,<8.3'],
            '6.4' => ['php' => '>=8.1,<8.2'],
            '5.4' => ['php' => '>=7.4,<8.0'],
        ],
    ];

    // Dependencies or variables to inject for specific combinations
    private const OVERRIDES = [
        'symfony' => [
            '>=7.0' => ['twig-svg-extension' => '2.0'],
        ],
    ];

    public function getIterator(): Traversable
    {
        // Coverage target
        $coverage = array_merge([
            // Only linux supports coverage generation
            'os' => 'ubuntu-latest',
            // Get stable versions o use latest
            'php' => '8.5',
            'symfony' => '8.1',
        ]);

        // Generate combinations
        foreach (self::cartesian(self::DIMENSIONS) as $combination) {
            // Apply constraints to discard invalid combinations
            foreach ($combination as $sourceDim => $sourceVer) {
                if ($constraints = self::CONSTRAINTS[$sourceDim][$sourceVer] ?? null) {
                    foreach ($constraints as $targetDim => $constraint) {
                        if (isset($combination[$targetDim]) && !self::satisfies($combination[$targetDim], $constraint)) {
                            continue 3; // Skip to the next cartesian combination
                        }
                    }
                }
            }

            // Apply conditional overrides for the current combination
            foreach (self::OVERRIDES as $sourceDim => $overrides) {
                if (isset($combination[$sourceDim])) {
                    foreach ($overrides as $constraint => $override) {
                        if (self::satisfies($combination[$sourceDim], $constraint)) {
                            $combination = array_merge($combination, $override);
                        }
                    }
                }
            }

            // Add static overrides
            $combination['dependencies'] ??= 'highest';
            if (empty(array_diff_assoc($coverage, $combination))) {
                $combination['coverage'] ??= 'pcov';
            }

            yield $combination;
        }
    }

    private static function satisfies(string $version, string $constraints): bool
    {
        foreach (explode(',', $constraints) as $constraint) {
            $constraint = trim($constraint);
            if (preg_match('/^([<>=!]+)\s*(.+)$/', $constraint, $matches)) {
                $operator = $matches[1];
                $constraint = $matches[2];
            } elseif (!version_compare($version, $constraint, '=')) {
                $operator = '=';
            }

            if (!version_compare($version, $constraint, $operator)) {
                return false;
            }
        }

        return true;
    }

    private static function cartesian(array $values, ?array $dims = null, array $state = []): Generator
    {
        $dims ??= array_keys($values);

        if (empty($dims)) {
            yield $state;

            return;
        }

        // Process current dimension
        $dim = array_shift($dims);
        foreach ($values[$dim] as $value) {
            yield from self::cartesian(
                $values,
                $dims,
                $state + [$dim => $value]
            );
        }
    }
}

echo json_encode([
    'include' => iterator_to_array(new MatrixGenerator()),
], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
