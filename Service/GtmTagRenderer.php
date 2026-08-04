<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the GoogleTagManager package.                           */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace GoogleTagManager\Service;

use Twig\Environment;

/**
 * Builds the raw GTM markup fragments (container, noscript, dataLayer pushes, JS listeners).
 * Stateless rendering primitives — the "what to render for the current page" decision lives
 * in {@see DataLayerProvider}.
 */
final readonly class GtmTagRenderer
{

    public function __construct(
        private Environment $twig,
    ){}
    /**
     * dataLayer push of a JSON payload built by {@see GoogleTagService}.
     * The payloads are JSON-encoded with JSON_HEX_APOS|JSON_HEX_QUOT, so they are safe
     * to embed inside the single-quoted JSON.parse() argument.
     */
    public function dataLayerPush(false|string|null $data): string
    {
        if (false === $data || null === $data || '' === $data) {
            return '';
        }

        return $this->twig->render('@GoogleTagManagerModule/theme-hook/push.html.twig',[
            'data' => $data
        ]);
    }

    /**
     * dataLayer push of a ShortCode placeholder ([google_tag_view_item] ...) resolved by
     * GoogleTagListener through the ShortCode module at KernelEvents::RESPONSE time.
     */
    public function shortCodePush(string $shortCodeTag): string
    {
        return $this->twig->render('@GoogleTagManagerModule/theme-hook/push.html.twig', [
            'data' => '['.$shortCodeTag.']'
        ]);
    }
}
