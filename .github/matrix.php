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
        // Update dimensions with current symfony releases
        $phpReleases = self::getPhpReleases();
        $symfonyReleases = self::getSymfonyReleases();
        $dimensions = array_merge(self::DIMENSIONS, [
            'php' => array_keys($phpReleases['versions'] ?? []) ?: self::DIMENSIONS['php'],
            'symfony' => array_keys($symfonyReleases['versions'] ?? []) ?: self::DIMENSIONS['symfony'],
        ]);

        // Coverage target
        $coverage = array_merge([
            // Only linux supports coverage generation
            'os' => 'ubuntu-latest',
            // Get stable versions o use latest
            'php' => $phpReleases['summary']['stable'] ?? $dimensions['php'][0],
            'symfony' => $symfonyReleases['summary']['stable'] ?? $dimensions['symfony'][0],
        ]);

        // Generate combinations
        foreach (self::cartesian($dimensions) as $combination) {
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

    private static function getPhpReleases(string $url = 'https://php.watch/api/v1/versions'): array
    {
        // Get data
        $json = self::fetchUrl($url);
        if (empty($json)) {
            return [];
        }

        $stable = null;
        $next = null;
        $versions = [];

        foreach ($json['data'] as $v) {
            if (!isset($v['name']) || !preg_match('/^\d+\.\d+$/', $v['name'])) {
                continue;
            }

            if ($v['isLatestVersion'] ?? false) {
                $stable = $v['name'];
            }
            if ($v['isNextVersion'] ?? false) {
                $next = $v['name'];
            }

            $versions[$v['name']] = $v;
        }

        uksort($versions, static fn (string $lhs, string $rhs): int => version_compare((string) $rhs, (string) $lhs));

        return [
            'summary' => [
                'stable' => $stable,
                'lts' => null,
                'next' => $next,
            ],
            'versions' => $versions,
        ];
    }

    private static function getSymfonyReleases(string $url = 'https://symfony.com/releases.json'): array
    {
        // Get data
        $json = self::fetchUrl($url);
        if (empty($json)) {
            return [];
        }

        $stable = $json['latest_stable_version'] ?? null;
        $lts = isset($json['symfony_versions']['lts'])
            ? preg_replace('/^(\d+\.\d+).*/', '$1', $json['symfony_versions']['lts'])
            : null;
        $next = isset($json['symfony_versions']['next'])
            ? preg_replace('/^(\d+\.\d+).*/', '$1', $json['symfony_versions']['next'])
            : null;

        $versions = [];
        foreach (array_merge(
            $json['supported_versions'] ?? [],
            $json['maintained_versions'] ?? [],
            $json['security_maintained_versions'] ?? []
        ) as $ver) {
            $versions[(string) $ver] = [
                'name' => (string) $ver,
                'isEOLVersion' => !in_array($ver, $json['supported_versions'], true),
                'isSecureVersion' => in_array($ver, $json['supported_versions'], true) || in_array($ver, $json['security_maintained_versions'], true),
                'isLatestVersion' => $ver === $stable,
                'isFutureVersion' => false,
                'isNextVersion' => $ver === $next,
                'requirements' => array_filter(['php' => $json['min_php_requirements'][$ver]]),
            ];
        }

        // Sort versions
        uksort($versions, static fn (string $lhs, string $rhs): int => version_compare((string) $rhs, (string) $lhs));

        return [
            'summary' => [
                'stable' => $stable,
                'lts' => $lts,
                'next' => $next,
            ],
            'versions' => $versions,
        ];
    }

    private static function fetchUrl(string $url): array
    {
        try {
            if (false === $json = @file_get_contents($url)) {
                /*$json = match ($url) {
                    'https://symfony.com/releases.json' => '{"symfony_versions":{"lts":"7.4.17","stable":"8.1.5","next":"8.2.0"},"latest_stable_version":"8.1","supported_versions":["6.4","7.4","8.1"],"maintained_versions":["6.4","7.4","8.1","8.2"],"security_maintained_versions":["5.4"],"flex_supported_versions":["3.4","4.0","4.1","4.2","4.3","4.4","5.0","5.1","5.2","5.3","5.4","6.0","6.1","6.2","6.3","6.4","7.0","7.1","7.2","7.3","7.4","8.0","8.1","8.2"],"min_php_requirements":{"3.4":"5.5.9","4.0":"7.1.3","4.1":"7.1.3","4.2":"7.1.3","4.3":"7.1.3","4.4":"7.1.3","5.0":"7.2.5","5.1":"7.2.5","5.2":"7.2.5","5.3":"7.2.5","5.4":"7.2.5","6.0":"8.0.2","6.1":"8.1.0","6.2":"8.1.0","6.3":"8.1.0","6.4":"8.1.0","7.0":"8.2.0","7.1":"8.2.0","7.2":"8.2.0","7.3":"8.2.0","7.4":"8.2.0","8.0":"8.4.0","8.1":"8.4.0","8.2":"8.4.0"}}',
                    'https://php.watch/api/v1/versions' => '{  "data": {    "70200": {      "versionId": 70200,      "name": "7.2",      "releaseDate": "2017-11-30",      "activeSupportEndDate": "2019-11-30",      "eolDate": "2020-11-30",      "isEOLVersion": true,      "isSecureVersion": false,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Unsupported"    },    "70300": {      "versionId": 70300,      "name": "7.3",      "releaseDate": "2018-12-06",      "activeSupportEndDate": "2020-12-06",      "eolDate": "2021-12-06",      "isEOLVersion": true,      "isSecureVersion": false,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Unsupported"    },    "70400": {      "versionId": 70400,      "name": "7.4",      "releaseDate": "2019-11-28",      "activeSupportEndDate": "2021-11-28",      "eolDate": "2022-11-28",      "isEOLVersion": true,      "isSecureVersion": false,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Unsupported"    },    "80000": {      "versionId": 80000,      "name": "8.0",      "releaseDate": "2020-11-26",      "activeSupportEndDate": "2022-11-26",      "eolDate": "2023-11-26",      "isEOLVersion": true,      "isSecureVersion": false,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Unsupported"    },    "80100": {      "versionId": 80100,      "name": "8.1",      "releaseDate": "2021-11-25",      "activeSupportEndDate": "2023-11-25",      "eolDate": "2025-12-31",      "isEOLVersion": true,      "isSecureVersion": false,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Unsupported"    },    "80200": {      "versionId": 80200,      "name": "8.2",      "releaseDate": "2022-12-08",      "activeSupportEndDate": "2024-12-31",      "eolDate": "2026-12-31",      "isEOLVersion": false,      "isSecureVersion": true,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Security-Fixes Only"    },    "80300": {      "versionId": 80300,      "name": "8.3",      "releaseDate": "2023-11-23",      "activeSupportEndDate": "2025-12-31",      "eolDate": "2027-12-31",      "isEOLVersion": false,      "isSecureVersion": true,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Security-Fixes Only"    },    "80400": {      "versionId": 80400,      "name": "8.4",      "releaseDate": "2024-11-21",      "activeSupportEndDate": "2026-12-31",      "eolDate": "2028-12-31",      "isEOLVersion": false,      "isSecureVersion": true,      "isLatestVersion": false,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Supported"    },    "80500": {      "versionId": 80500,      "name": "8.5",      "releaseDate": "2025-11-20",      "activeSupportEndDate": "2027-12-31",      "eolDate": "2029-12-31",      "isEOLVersion": false,      "isSecureVersion": true,      "isLatestVersion": true,      "isFutureVersion": false,      "isNextVersion": false,      "statusLabel": "Supported (Latest)"    },    "80600": {      "versionId": 80600,      "name": "8.6",      "releaseDate": null,      "activeSupportEndDate": null,      "eolDate": null,      "isEOLVersion": false,      "isSecureVersion": false,      "isLatestVersion": false,      "isFutureVersion": true,      "isNextVersion": true,      "statusLabel": "Upcoming Release"    }  },  "_documentation": "https://php.watch/api#versions",  "_description": "Latest PHP versions, along with their support/security/EOL status and release/EOL/active dates."}',
                    default => false,
                };*/

                if (false === $json) {
                    return [];
                }
            }

            return json_decode($json, true, 512, \JSON_THROW_ON_ERROR) ?: [];
        } catch (JsonException) {
            return [];
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

echo json_encode(
    ['include' => iterator_to_array(new MatrixGenerator())],
    \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR
);
