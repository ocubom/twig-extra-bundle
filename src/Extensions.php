<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle;

use Ocubom\Twig\Extension\HtmlExtension;
use Ocubom\Twig\Extension\SvgExtension;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;
use Twig\Error\SyntaxError;

final class Extensions
{
    private const EXTENSIONS = [
        'html' => [
            'name' => 'html',
            'class' => HtmlExtension::class,
            'class_name' => 'OcubomHtmlExtension',
            'package' => 'ocubom/twig-html-extension',
            'filters' => ['html_attributes', 'html_compress'],
            'functions' => [],
        ],
        'svg' => [
            'name' => 'svg',
            'class' => SvgExtension::class,
            'class_name' => 'OcubomSvgExtension',
            'package' => 'ocubom/twig-svg-extension',
            'filters' => ['svg_symbols', 'fontawesome'],
            'functions' => ['fa', 'svg'],
        ],
        'webpack_encore' => [
            'name' => 'webpack_encore',
            'class' => EntryFilesTwigExtension::class, // Use the official twig extension
            'class_name' => 'OcubomWebpackEncoreExtension',
            'package' => 'symfony/webpack-encore-bundle', // Needed to enable this
            'filters' => [],
            'functions' => ['encore_entry_css_source', 'encore_entry_js_source'],
        ],
    ];

    public static function getClasses(): array
    {
        return array_column(self::EXTENSIONS, 'class', 'name');
    }

    public static function suggestFilter(string $name): bool
    {
        foreach (self::EXTENSIONS as $extension) {
            if (isset($extension['filters']) && in_array($name, $extension['filters'])) {
                throw new SyntaxError(sprintf('The "%s" filter is part of the %s, which is not installed/enabled; try running "composer require %s".', $name, $extension['class_name'], $extension['package']));
            }
        }

        return false;
    }

    public static function suggestFunction(string $name): bool
    {
        foreach (self::EXTENSIONS as $extension) {
            if (isset($extension['functions']) && in_array($name, $extension['functions'])) {
                throw new SyntaxError(sprintf('The "%s" function is part of the %s, which is not installed/enabled; try running "composer require %s".', $name, $extension['class_name'], $extension['package']));
            }
        }

        return false;
    }
}
