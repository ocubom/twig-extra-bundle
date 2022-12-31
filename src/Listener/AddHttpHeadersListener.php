<?php

/*
 * This file is part of ocubom/twig-extra-bundle
 *
 * © Oscar Cubo Medina <https://ocubom.github.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ocubom\TwigExtraBundle\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AddHttpHeadersListener implements EventSubscriberInterface
{
    protected array $rules = [];

    public function __construct(array ...$rules)
    {
        $this->rules = \func_get_args() ?: [];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (empty($this->rules) || !$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!is_string($content)) {
            return; // Ignore special (binary or streamed) responses
        }

        $headers = $response->headers;
        $format = $headers->get('content-type') ?: ($event->getRequest()->getRequestFormat() ?? 'text/html');
        $format = explode(';', $format)[0];

        // Add custom headers and filter content
        foreach ($this->rules as $rule) {
            $content = $this->applyRule($rule, $content, $headers, $format);
        }

        try {
            $response->setContent($content);
        } catch (\LogicException $err) { // @codeCoverageIgnore
            // Just ignore exception
        }
    }

    /** @codeCoverageIgnore */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function applyRule(array $rule, string $content, ResponseHeaderBag $headers, string $format): string
    {
        if (!$rule['enabled'] || (isset($rule['formats']) && !\in_array($format, $rule['formats'], true))) {
            return $content; // Ignore disabled or unsupported rules
        }

        if (isset($rule['pattern'])) {
            $content = preg_replace_callback(
                $rule['pattern'],
                function (array $match) use ($headers, $rule): string {
                    // Set header value
                    $value = vsprintf(isset($rule['value']) ? $rule['value'] : '%s', $match);
                    $headers->set($rule['name'], $value);

                    // Replace matched text
                    return vsprintf(isset($rule['replace']) ? $rule['replace'] : '%s', $match);
                },
                $content
            );
        } else {
            // Add value
            $headers->set($rule['name'], isset($rule['value']) ? $rule['value'] : '');
        }

        return $content;
    }
}
