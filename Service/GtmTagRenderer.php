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

use Thelia\Tools\URL;

/**
 * Builds the raw GTM markup fragments (container, noscript, dataLayer pushes, JS listeners).
 * Stateless rendering primitives — the "what to render for the current page" decision lives
 * in {@see DataLayerProvider}.
 */
class GtmTagRenderer
{
    public function renderContainer(string $containerId): string
    {
        return '<!-- Google Tag Manager -->'
            ."<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':"
            ."new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],"
            ."j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src="
            ."'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);"
            ."})(window,document,'script','dataLayer','".$containerId."');</script>"
            .'<!-- End Google Tag Manager -->';
    }

    public function renderNoscript(string $containerId): string
    {
        return '<!-- Google Tag Manager (noscript) -->'
            ."<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=".$containerId."' "
            ."height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>"
            .'<!-- End Google Tag Manager (noscript) -->';
    }

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

        return '<script>window.dataLayer = window.dataLayer || [];'
            ."dataLayer.push({ ecommerce: null });dataLayer.push(JSON.parse('".$data."'));</script>";
    }

    /**
     * dataLayer push of a ShortCode placeholder ([google_tag_view_item] ...) resolved by
     * GoogleTagListener through the ShortCode module at KernelEvents::RESPONSE time.
     */
    public function shortCodePush(string $shortCodeTag): string
    {
        return '<script>window.dataLayer = window.dataLayer || [];'
            ."dataLayer.push({ ecommerce: null });dataLayer.push(JSON.parse('[".$shortCodeTag."]'));</script>";
    }

    public function renderSelectItem(): string
    {
        $url = URL::getInstance()?->absoluteUrl('/googletagmanager/getItem');

        return <<<HTML
<script>
document.addEventListener("DOMContentLoaded", function(e) {
    const products = document.querySelectorAll("a.SingleProduct, .SingleProduct a");
    let processLinkClick = async function(e) {
        let targetUrl;
        if (window['google_tag_manager']) {
            e.preventDefault();
            targetUrl = e.currentTarget.href;
            if (undefined === targetUrl) {
                targetUrl = e.currentTarget.getElementsByTagName('a')[0].href;
            }
            const response = await fetch('$url', {
                method: "POST",
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ productUrl: targetUrl })
            })
            const resultJson = await response.json();
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                event: 'select_item',
                ecommerce: { items: JSON.parse(resultJson) },
                eventCallback: function() { window.location = targetUrl; },
                eventTimeout : 2000
            });
        }
    };
    for (let i = 0; i < products.length; i++) {
        products[i].addEventListener('click', processLinkClick, false);
    }
});
</script>
HTML;
    }

    public function renderAddToCart(): string
    {
        $url = URL::getInstance()?->absoluteUrl('/googletagmanager/getCartItem');

        return <<<HTML
<script>
let getCartItem = async function(e) {
    const response = await fetch('$url', {
        method: "POST",
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ pseId: e.detail.pse, quantity: e.detail.quantity })
    })
    return await response.json();
}
let addPseToCart = async function(e) {
    const resultJson = await getCartItem(e)
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event: 'add_to_cart', ecommerce: JSON.parse(resultJson) });
}
let removePseFromCart = async function(e) {
    const resultJson = await getCartItem(e)
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event: 'remove_from_cart', ecommerce: JSON.parse(resultJson) });
}
document.addEventListener('addPseToCart', addPseToCart);
document.addEventListener('removePseFromCart', removePseFromCart);
</script>
HTML;
    }
}
