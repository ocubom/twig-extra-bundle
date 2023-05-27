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

use Ocubom\TwigExtraBundle\Listener\AddHttpHeadersListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AddHttpHeadersTest extends TestCase
{
    public static $rules = [
        'x-robots' => [
            'name' => 'X-Robots-Tag',
            'pattern' => '@[\p{Zs}]*<meta\s+(?:name="robots"\s+content="([^"]+)"|content="([^"]+)"\s+name="robots")\s*/?\s*>\p{Zs}*\n?@i',
            'value' => '%2$s',
            'replace' => '%1$s',
            'formats' => ['text/html'],
            'enabled' => true,
        ],
        'x-ua-compatible' => [
            'name' => 'X-UA-Compatible',
            'pattern' => '@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i',
            'value' => '%2$s',
            'replace' => '',
            'formats' => ['text/html'],
            'enabled' => true,
        ],
    ];

    /** @dataProvider provideResponseEvent */
    public function testAddHeaders(ResponseEvent $event, array $rules, array $expected, string $body)
    {
        (new AddHttpHeadersListener(...$rules))->onKernelResponse($event);

        foreach ($expected as $header => $value) {
            $this->assertMatchesRegularExpression(
                '@'.$header.':\s+'.$value.'\r\n@Uis',
                $event->getResponse()->headers,
                sprintf('Missing "%s" header with value "%s"', $header, $value),
            );
        }

        $this->assertEquals($body, $event->getResponse()->getContent());
    }

    public function provideResponseEvent()
    {
        $kernel = $this->getMockBuilder(HttpKernel::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();

        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();

        // Configure the stub.
        $request->method('getRequestFormat')
            ->willReturn('text/html');

        yield 'no rules' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                new Response(implode("\n", [
                    '<html lang="en">',
                    '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                    '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                    '</html>',
                ])),
            ),
            [],
            [],
            implode("\n", [
                '<html lang="en">',
                '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                '</html>',
            ]),
        ];

        yield 'example rules' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                new Response(implode("\n", [
                    '<html lang="en">',
                    '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                    '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                    '</html>',
                ])),
            ),
            [
                [
                    'name' => 'X-Robots-Tag',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:name="robots"\s+content="([^"]+)"|content="([^"]+)"\s+name="robots")\s*/?\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '%1$s',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
                [
                    'name' => 'X-UA-Compatible',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
            ],
            [
                'X-Robots-Tag' => 'noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex',
            ],
            implode("\n", [
                '<html lang="en">',
                '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                '</html>',
            ]),
        ];

        yield 'disabled rules' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                new Response(implode("\n", [
                    '<html lang="en">',
                    '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                    '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                    '</html>',
                ])),
            ),
            [
                [
                    'name' => 'X-Robots-Tag',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:name="robots"\s+content="([^"]+)"|content="([^"]+)"\s+name="robots")\s*/?\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '%1$s',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
                [
                    'name' => 'X-UA-Compatible',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '',
                    'formats' => ['text/html'],
                    'enabled' => false,
                ],
            ],
            [
                'X-Robots-Tag' => 'noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex',
            ],
            implode("\n", [
                '<html lang="en">',
                '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                '</html>',
            ]),
        ];

        yield 'not matching formats' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                new Response(implode("\n", [
                    '<html lang="en">',
                    '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                    '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                    '</html>',
                ])),
            ),
            [
                [
                    'name' => 'X-Robots-Tag',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:name="robots"\s+content="([^"]+)"|content="([^"]+)"\s+name="robots")\s*/?\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '%1$s',
                    'formats' => ['application/pdf'],
                    'enabled' => true,
                ],
                [
                    'name' => 'X-UA-Compatible',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '',
                    'formats' => ['application/pdf'],
                    'enabled' => true,
                ],
            ],
            [
            ],
            implode("\n", [
                '<html lang="en">',
                '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                '</html>',
            ]),
        ];

        yield 'subrequest' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::SUB_REQUEST,
                new Response(implode("\n", [
                    '<html lang="en">',
                    '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                    '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                    '</html>',
                ])),
            ),
            [
                [
                    'name' => 'X-Robots-Tag',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:name="robots"\s+content="([^"]+)"|content="([^"]+)"\s+name="robots")\s*/?\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '%1$s',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
                [
                    'name' => 'X-UA-Compatible',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
            ],
            [],
            implode("\n", [
                '<html lang="en">',
                '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                '</html>',
            ]),
        ];

        yield 'streamed response' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                new StreamedResponse(),
            ),
            [
                [
                    'name' => 'X-Robots-Tag',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:name="robots"\s+content="([^"]+)"|content="([^"]+)"\s+name="robots")\s*/?\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '%1$s',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
                [
                    'name' => 'X-UA-Compatible',
                    'pattern' => '@[\p{Zs}]*<meta\s+(?:http-equiv="X-UA-Compatible"\s+content="([^"]+)"|content="([^"]+)"\s+http-equiv="X-UA-Compatible")\s*>\p{Zs}*\n?@i',
                    'value' => '%2$s',
                    'replace' => '',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
            ],
            [],
            '',
        ];

        yield 'add static header' => [
            new ResponseEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                new Response(implode("\n", [
                    '<html lang="en">',
                    '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                    '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                    '</html>',
                ])),
            ),
            [
                [
                    'name' => 'X-StaticHeader',
                    'value' => 'STATIC',
                    'replace' => '',
                    'formats' => ['text/html'],
                    'enabled' => true,
                ],
            ],
            [
                'X-StaticHeader' => 'STATIC',
            ],
            implode("\n", [
                '<html lang="en">',
                '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noodp,notranslate,noimageindex" />',
                '<meta http-equiv="X-UA-Compatible" content="ie=edge,requiresActiveX=true">',
                '</html>',
            ]),
        ];
    }
}
