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

/**
 * What the theme hook and the Twig functions of the module ask of the dataLayer provider, so that a project can decorate it
 * (the default implementation is a final class) to change when an event is pushed.
 *
 * {@see DataLayerProvider::renderHead()} is {@see self::renderPageEvents()} followed by {@see self::renderCheckoutEvents()}.
 * A decorator that wants other moments for the checkout events replaces the second and leaves the first to the module.
 */
interface DataLayerProviderInterface
{
    /**
     * Everything pushed in the head, in the order of the page: the pushes of the page and the ones of the checkout.
     */
    public function renderHead(): string;

    /**
     * The pushes that follow the page itself: `thelia_page_view`, the list and product placeholders, the login event.
     */
    public function renderPageEvents(): string;

    /**
     * The pushes the checkout routes trigger: `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info` and
     * `purchase`.
     */
    public function renderCheckoutEvents(): string;

    public function renderJsInit(): string;

    public function trackProduct(int|string|null $productId): string;
}
