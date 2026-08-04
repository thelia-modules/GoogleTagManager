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

namespace GoogleTagManager\Twig;

use GoogleTagManager\Service\DataLayerProvider;
use GoogleTagManager\Service\GtmConfig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the GTM rendering to Twig templates. Call these functions in your front theme
 * (the module no longer relies on Thelia hooks):
 *   - {{ gtm_track_product(product.id) }}  in the product page template
 */
class GoogleTagManagerExtension extends AbstractExtension
{
    public function __construct(
        private readonly GtmConfig          $config,
        private readonly DataLayerProvider  $dataLayerProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gtm_head', [$this, 'head'], ['is_safe' => ['html']]),
            new TwigFunction('gtm_js_init', [$this, 'jsInit'], ['is_safe' => ['html']]),
            new TwigFunction('gtm_track_product', [$this, 'trackProduct'], ['is_safe' => ['html']]),
        ];
    }

    public function head(): string
    {
        if (!$this->config->isEnabled()) {
            return '';
        }

        return $this->dataLayerProvider->renderHead();
    }

    public function jsInit(): string
    {
        if (!$this->config->isEnabled()) {
            return '';
        }

        return $this->dataLayerProvider->renderJsInit();
    }

    public function trackProduct(int|string|null $productId): string
    {
        if ($this->config->isEnabled()) {
            $this->dataLayerProvider->trackProduct($productId);
        }

        return '';
    }
}
