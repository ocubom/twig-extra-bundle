<h1 align="center">

[
<span id="project-title">ocubom/twig-extra-bundle</span>
](https://github.com/ocubom/twig-extra-bundle/)

</h1>
<div align="center">

_A Symfony bundle for custom extra Twig extensions._

[![Version][packagist-img]][packagist-url]
[![PHP version][packagist-php-img]][packagist-php-url]
[![License][license-img]][license-url]
[![Build status][workflow-build-img]][workflow-build-url]
[![Coverage][coverage-img]][coverage-url]

[![Downloads][packagist-dl-img]][packagist-dl-url]
[![Issues][issues-img]][issues-url]
[![Stargazers][stars-img]][stars-url]
[![Forks][forks-img]][forks-url]
[![Contributors][contributors-img]][contributors-url]

[**Explore the docs »**](https://github.com/ocubom/twig-extra-bundle/)
·
[Report Bug](https://github.com/ocubom/twig-extra-bundle/issues)
·
[Request Feature](https://github.com/ocubom/twig-extra-bundle/issues)

<table align="center"><tr><td align="left">
<details>
<summary id="toc">Table of Contents</summary>

* [About ocubom/twig-extra-bundle](#about-ocubomtwig-extra-bundle)
* [Getting Started](#getting-started)
    * [Prerequisites](#prerequisites)
    * [Installation](#installation)
* [Usage](#usage)
* [Roadmap](#roadmap)
* [Support](#support)
* [Authorship](#authorship)
* [License](#license)

</details>
</td></tr></table>

</div>

## About ocubom/twig-extra-bundle

[ocubom/twig-extra-bundle](https://github.com/ocubom/twig-extra-bundle/) allows
the use of several custom Twig extensions with zero configuration.


This suite started as an internal helper based on [nochso/html-compress-twig][]
to bring [wyrihaximus/html-compress][] support to Twig 3.0.
Over time, as several projects embedded and modified this class independently,
development became fragmented and difficult to maintain.
To resolve this, several standalone open-source extensions were created to
unify those efforts.

This repository provides a Symfony bundle to easily integrate all those
extensions into a Symfony project.
The bundle continues to evolve to configure additional custom extensions,
following the philosophy of [twig/extra-bundle][].

## Getting Started

### Prerequisites

* **PHP** >= 7.4 (tested up to 8.5)
* **Composer** >= 2.0

### Installation

Install the package via [composer][]:

```sh
composer require ocubom/twig-extra-bundle
```

You must also install the Twig extensions you want to use:

```sh
# For HTML filters (`html_attributes`, `html_minify`)
composer require ocubom/twig-html-extension

# For SVG filters (`svg`, `svg_symbols`, `fontawesome`, `iconify`)
composer require ocubom/twig-svg-extension
```

#### Optional Dependencies

You can install additional packages to plug extra functionality:

* For HTML minification ([ocubom/twig-html-extension][]):

```sh
composer require matthiasmullie/minify  ## scripts & styles
composer require wikimedia/minify       ## scripts & styles
composer require tedivm/jshrink         ## scripts only
composer require tubalmartin/cssmin     ## styles only
```

* For SVG sanitization ([ocubom/twig-svg-extension][]):

```sh
composer require enshrined/svg-sanitize
```

## Usage

Enable the bundle by adding it to the list of registered bundles in the
`config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Ocubom\TwigExtraBundle\OcubomTwigExtraBundle::class => ['all' => true],
];
```

Just create the file `config/packages/ocubom_twig_extra.yaml` using the configuration reference:

```console
bin/console config:dump-reference ocubom_twig_extra
```

Or you can use the [example configuration][] provided.

> [!NOTE]
> This configuration will be installed if your project uses [endroid/installer][]

## Roadmap

See the [open issues](https://github.com/ocubom/twig-extra-bundle/issues) for a list of proposed features (and known issues).

- [Top Feature Requests](https://github.com/ocubom/twig-extra-bundle/issues?q=label%3Aenhancement+is%3Aopen+sort%3Areactions-%2B1-desc)
- [Top Bugs](https://github.com/ocubom/twig-extra-bundle/issues?q=is%3Aissue+is%3Aopen+label%3Abug+sort%3Areactions-%2B1-desc)
- [Newest Bugs](https://github.com/ocubom/twig-extra-bundle/issues?q=is%3Aopen+is%3Aissue+label%3Abug)

## Support

Reach out to the maintainer at [GitHub Issues](https://github.com/ocubom/twig-extra-bundle/issues/new?assignees=&labels=question&template=04_SUPPORT_QUESTION.md&title=support%3A+).

## Authorship

[Oscar Cubo Medina](https://github.com/ocubom) — https://ocubom.github.io

For a full list of all authors and contributors, see [the contributors page](https://github.com/ocubom/twig-extra-bundle/contributors).

## License

Distributed under the MIT License.

See [LICENSE][] for more information.


[LICENSE]: https://github.com/ocubom/twig-extra-bundle/blob/master/LICENSE
[example configuration]: https://github.com/ocubom/twig-extra-bundle/blob/main/.install/symfony/config/packages/ocubom_twig_extra.yaml

<!-- Links -->
[composer]: https://getcomposer.org/
[endroid/installer]: https://packagist.org/packages/endroid/installer

<!-- Packagist links -->
[nochso/html-compress-twig]: https://packagist.org/packages/nochso/html-compress-twig
[twig/extra-bundle]: https://packagist.org/packages/twig/extra-bundle
[wyrihaximus/html-compress]: https://packagist.org/packages/wyrihaximus/html-compress
[ocubom/twig-html-extension]: https://packagist.org/packages/ocubom/twig-html-extension
[ocubom/twig-svg-extension]: https://packagist.org/packages/ocubom/twig-svg-extension


<!-- Project Badges -->
[contributors-img]:     https://img.shields.io/github/contributors/ocubom/twig-extra-bundle.svg?style=for-the-badge&color=blue
[contributors-url]:     https://github.com/ocubom/twig-extra-bundle/graphs/contributors
[forks-img]:            https://img.shields.io/github/forks/ocubom/twig-extra-bundle.svg?style=for-the-badge
[forks-url]:            https://github.com/ocubom/twig-extra-bundle/network/members
[issues-img]:           https://img.shields.io/github/issues/ocubom/twig-extra-bundle.svg?style=for-the-badge
[issues-url]:           https://github.com/ocubom/twig-extra-bundle/issues
[license-img]:          https://img.shields.io/github/license/ocubom/twig-extra-bundle.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white
[license-url]:          https://github.com/ocubom/twig-extra-bundle/blob/main/LICENSE
[stars-img]:            https://img.shields.io/github/stars/ocubom/twig-extra-bundle.svg?style=for-the-badge
[stars-url]:            https://github.com/ocubom/twig-extra-bundle/stargazers
[workflow-build-img]:   https://img.shields.io/github/actions/workflow/status/ocubom/twig-extra-bundle/ci.yaml?style=for-the-badge&logo=githubactions&branch=main&logoColor=white
[workflow-build-url]:   https://github.com/ocubom/twig-extra-bundle/actions/

[packagist-img]:        https://img.shields.io/packagist/v/ocubom/twig-extra-bundle.svg?style=for-the-badge&logo=packagist&logoColor=white
[packagist-url]:        https://packagist.org/packages/ocubom/twig-extra-bundle
[packagist-php-img]:    https://img.shields.io/packagist/dependency-v/ocubom/twig-extra-bundle/php?style=for-the-badge&logo=php&logoColor=white
[packagist-php-url]:    https://packagist.org/packages/ocubom/twig-extra-bundle
[packagist-dl-img]:     https://img.shields.io/packagist/dt/ocubom/twig-extra-bundle?style=for-the-badge
[packagist-dl-url]:     https://packagist.org/packages/ocubom/twig-extra-bundle

[coverage-img]:         https://img.shields.io/codecov/c/github/ocubom/twig-extra-bundle?style=for-the-badge&logo=codecov&logoColor=white
[coverage-url]:         https://codecov.io/github/ocubom/twig-extra-bundle/
