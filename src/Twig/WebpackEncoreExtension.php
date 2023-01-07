<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\Twig;

use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WebpackEncoreExtension extends AbstractExtension
{
    private EntrypointLookupCollectionInterface $collection;

    /** @var string[] */
    private array $buildPaths;

    /**
     * @param string[] $buildPaths
     */
    public function __construct(EntrypointLookupCollectionInterface $collection, array $buildPaths)
    {
        $this->collection = $collection;
        $this->buildPaths = $buildPaths;
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        // Alternative implementation to https://github.com/symfony/webpack-encore-bundle/pull/91
        // Based on https://symfonycasts.com/screencast/mailer/encore-inline_css
        return [
            new TwigFunction('encore_entry_css_source', [$this, 'getWebpackCssSource']),
            new TwigFunction('encore_entry_js_source', [$this, 'getWebpackJsSource']),
        ];
    }

    /**
     * @return string[]
     */
    public function getWebpackCssFiles(string $entryName, string $entrypointName = '_default'): array
    {
        $entrypoint = $this->getEntrypointLookup($entrypointName);

        try {
            $entrypoint->reset();

            return $entrypoint->getCssFiles($entryName);
        } finally {
            $entrypoint->reset(); // Ensure reset after access files
        }
    }

    public function getWebpackCssSource(string $entryName, string $entrypointName = '_default'): string
    {
        return $this->concatenateFileSources($this->getWebpackCssFiles($entryName, $entrypointName));
    }

    /**
     * @return string[]
     */
    public function getWebpackJsFiles(string $entryName, string $entrypointName = '_default'): array
    {
        $entrypoint = $this->getEntrypointLookup($entrypointName);

        try {
            $entrypoint->reset();

            return $entrypoint->getJavaScriptFiles($entryName);
        } finally {
            $entrypoint->reset();
        }
    }

    public function getWebpackJsSource(string $entryName, string $entrypointName = '_default'): string
    {
        return $this->concatenateFileSources($this->getWebpackJsFiles($entryName, $entrypointName));
    }

    /**
     * @param string[] $files
     */
    private function concatenateFileSources(array $files): string
    {
        return array_reduce(
            $files,
            function ($source, $entry) {
                if (empty(parse_url($entry)['scheme'])) {
                    foreach ($this->buildPaths as $path) {
                        if (is_readable($path.'/'.$entry)) {
                            $entry = $path.$entry;
                            break;
                        }
                    }
                }

                return $source.file_get_contents($entry);
            },
            ''
        );
    }

    private function getEntrypointLookup(string $entrypointName): EntrypointLookupInterface
    {
        return $this->collection->getEntrypointLookup($entrypointName);
    }
}
