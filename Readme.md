# Google Tag Manager

This module pushes the Google Analytics 4 e-commerce events of a Thelia shop to a Google Tag Manager dataLayer.

## Installation

### Composer

```
composer require thelia/google-tag-manager-module:^4.0
```

The module needs the ShortCode module (2.0 or later): the `view_item` and `view_item_list` pushes are resolved through it once the response is built.

## Usage

You need to configure the id from your Google tag manager account in the thelia administration panel.\
It should look like ```GTM-XXXX```. Nothing is rendered while this id is empty.

The module injects its scripts through theme hooks: as long as your front theme declares the
hook points below, there is nothing to add to your templates.

| Theme hook | Where the theme declares it | What the module renders |
|------------|-----------------------------|-------------------------|
| `layout.head.bottom` | in `<head>` | GTM container script, then the dataLayer pushes of the page (see Events below) |
| `layout.body.top` | right after `<body>` | GTM `<noscript>` fallback |
| `layout.body.bottom` | before `</body>` | `select_item` / `add_to_cart` event listeners |
| `product.bottom` | product page, with a `product` parameter | registers the viewed product (enables the `view_item` event) |

The `flexy` front-office theme declares all four out of the box:

```twig
{# templates/frontOffice/flexy/base.html.twig #}
{{ theme_hook('layout.head.bottom', {breadcrumb}) }}
{{ theme_hook('layout.body.top') }}
{{ theme_hook('layout.body.bottom') }}

{# templates/frontOffice/flexy/product.html.twig #}
{{ theme_hook('product.bottom', {product: product}) }}
```

`product.bottom` must receive the product, otherwise the `view_item` event is silently skipped.

## Events

| Event | Pushed on | Items |
|-------|-----------|-------|
| `thelia_page_view` | every page, with the page type and the logged-in customer | none |
| `view_item_list` | `category`, `brand` and `search` views | the products of the listing |
| `view_item` | the product page | the viewed product |
| `select_item` | a click on a product link of a listing | the product behind the link |
| `add_to_cart`, `remove_from_cart` | the two JS custom events described below | the product sale element and its quantity |
| `view_cart` | the `checkout_cart` route | the cart lines |
| `begin_checkout` | the `checkout_delivery` route | the cart lines |
| `add_shipping_info` | the `checkout_invoice` route | the cart lines |
| `add_payment_info` | the `checkout_payment` route | the cart lines |
| `purchase` | the `checkout_pay` or `checkout_confirm` route, once, for the order placed in the session | the order lines |
| `thelia_auth_success` | the page following a login or a registration | none |

### Items

Every item carries `item_id`, `item_name`, `item_brand`, `affiliation`, `price`, `currency`, `quantity`,
the category path (`item_category`, `item_category2`...) and `item_variant` when the line is a combination.

`price` is the unit price before tax. Listing, product, cart and add to cart items round it to the cent;
purchase items keep six decimals, so that price times quantity gives back the amount the line was invoiced.
The `value` of an event is the taxed amount of the cart or of the order, and, like the `tax` and `shipping`
of the purchase, it is rounded to the cent.

`quantity` is the quantity of the line: the cart line, the order line, or the quantity sent by the
add to cart event.

### Reshaping the items from your theme or module

A project that needs its items shaped differently (a fixed quantity, another price rule) can decorate
`GoogleTagManager\Service\GoogleTagService` instead of patching the module. `getProductItem()` and
`getOrderProductItem()` build every item the module pushes, whatever the event, so overriding these two
covers listings, product page, cart, checkout steps, add to cart and purchase at once.

The service has no interface: the decorator extends the class and lets the module compute the item
first. The example below reports every item as a single unit, which a shop selling by weight needs
(its lines carry grams). The price is left as the module computed it.

```php
<?php

declare(strict_types=1);

namespace FlexyBundle\Service\GoogleTag;

use GoogleTagManager\Service\GoogleTagService;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

#[AsDecorator(decorates: GoogleTagService::class, onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final class SingleUnitGoogleTagService extends GoogleTagService
{
    public function getProductItem(
        Product $product,
        Lang $lang,
        Currency $currency,
        ?ProductSaleElements $pse = null,
        $quantity = null,
        $itemList = false,
        $taxed = false,
        ?Country $country = null
    ): array {
        return $this->asSingleUnit(
            parent::getProductItem($product, $lang, $currency, $pse, $quantity, $itemList, $taxed, $country)
        );
    }

    public function getOrderProductItem(
        OrderProduct $orderProduct,
        Lang $lang,
        Currency $currency,
        $quantity = null,
        $itemList = false,
        $taxed = false,
        ?Country $country = null
    ): array {
        return $this->asSingleUnit(
            parent::getOrderProductItem($orderProduct, $lang, $currency, $quantity, $itemList, $taxed, $country)
        );
    }

    private function asSingleUnit(array $item): array
    {
        $item['quantity'] = 1;

        return $item;
    }
}
```

Three things to know about this setup:

- The class lives in a directory your theme or module already registers as services with autowiring
  and autoconfiguration on (`src/` of a Flexy theme, or the directory `configureServices()` loads in a
  module). Nothing else to declare: the attribute registers the decoration.
- `onInvalid: IGNORE_ON_INVALID_REFERENCE` drops the decorator when this module is absent or disabled,
  so the shop keeps running without it.
- The constructor is inherited: the container injects the module's own dependencies. If you need one
  of yours, redeclare the constructor, add your dependency, and call `parent::__construct()` with the
  three the module expects.

Check the wiring on the compiled container: `php Thelia debug:container 'GoogleTagManager\Service\GoogleTagService'`
must list your class, with a `container.decorator` tag.

### Themes without theme hooks

If your theme does not declare those hook points, the same rendering is available as Twig functions
you place yourself:

| Function | Where | Equivalent to |
|----------|-------|---------------|
| `{{ gtm_head() }}` | in `<head>` | the dataLayer pushes of `layout.head.bottom` |
| `{{ gtm_js_init() }}` | before `</body>` | `layout.body.bottom` |
| `{{ gtm_track_product(product.id) }}` | product page template | `product.bottom` |

Do not use both mechanisms at once. `gtm_head()` is already called by the template rendered on
`layout.head.bottom`, so adding it to a theme that declares that hook pushes `page_view` twice.

This fallback has two limits. `gtm_head()` outputs the dataLayer pushes only, so you have to add the
GTM container `<script>` and the `<noscript>` iframe to your layout yourself. And there is no Twig
function for the `<noscript>` fallback.

### Tracking add to cart / remove from cart

The `add_to_cart` and `remove_from_cart` events are driven by two JS custom events you dispatch from
your "Add to cart" / "Remove from cart" buttons:

```js
document.dispatchEvent(new CustomEvent('addPseToCart', {
    detail: { pse: pseId, quantity },
}));

document.dispatchEvent(new CustomEvent('removePseFromCart', {
    detail: { pse: pseId, quantity },
}));
```

From a live component, dispatch the same event server side:

```php
$this->dispatchBrowserEvent('addPseToCart', [
    'pse' => $this->pseId,
    'quantity' => $quantity,
]);
```

### Tracking select_item on listings

On listing views (`category`, `brand`, `search`, `folder`, `content`, `page`) the module binds the
`select_item` event to product links matching `a.ProductCard, .ProductCard a`. Adapt
`templates/frontOffice/default/assets/js/getItem.js` if your theme uses different markup.
