# Google Tag Manager

This module is made to use the Google Tag Manager / Google Analytics 4.

## Installation

### Composer

```
composer require thelia/google-tag-manager-module:^4.0
```

## Usage

You need to configure the id from your Google tag manager account in the thelia administration panel.\
It should look like ```GTM-XXXX```. Nothing is rendered while this id is empty.

The module injects its scripts through **theme hooks**: as long as your front theme declares the
hook points below, there is nothing to add to your templates.

| Theme hook | Where the theme declares it | What the module renders |
|------------|-----------------------------|-------------------------|
| `layout.head.bottom` | in `<head>` | GTM container script + dataLayer pushes (`page_view`, `view_item`, `view_item_list`, `login`, `view_cart`, `begin_checkout`, `purchase`…) |
| `layout.body.top` | right after `<body>` | GTM `<noscript>` fallback |
| `layout.body.bottom` | before `</body>` | `select_item` / `add_to_cart` event listeners |
| `product.bottom` | product page, with a `product` parameter | registers the viewed product (enables the `view_item` event) |

The `work` front-office theme declares all four out of the box:

```twig
{# templates/frontOffice/work/base.html.twig #}
{{ theme_hook('layout.head.bottom', {breadcrumb}) }}
{{ theme_hook('layout.body.top') }}
{{ theme_hook('layout.body.bottom') }}

{# templates/frontOffice/work/product.html.twig #}
{{ theme_hook('product.bottom', {product: product}) }}
```

`product.bottom` must receive the product, otherwise the `view_item` event is silently skipped.

### Themes without theme hooks

If your theme does not declare those hook points, the same rendering is available as Twig functions
you place yourself:

| Function | Where | Equivalent to |
|----------|-------|---------------|
| `{{ gtm_head() }}` | in `<head>` | the dataLayer pushes of `layout.head.bottom` |
| `{{ gtm_js_init() }}` | before `</body>` | `layout.body.bottom` |
| `{{ gtm_track_product(product.id) }}` | product page template | `product.bottom` |

**Do not use both mechanisms at once.** `gtm_head()` is already called by the template rendered on
`layout.head.bottom`, so adding it to a theme that declares that hook pushes `page_view` twice.

Two caveats on this fallback: `gtm_head()` outputs the dataLayer pushes only — you have to add the
GTM container `<script>` and the `<noscript>` iframe to your layout yourself — and there is no Twig
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

### Tracking select_item on listings

On listing views (`category`, `brand`, `search`, `folder`, `content`, `page`) the module binds the
`select_item` event to product links matching `a.ProductCard, .ProductCard a`. Adapt
`templates/frontOffice/default/assets/js/getItem.js` if your theme uses different markup.
