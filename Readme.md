# Google Tag Manager

This module is made to use the Google Tag Manager / Google Analytics 4.

## Installation

### Composer

```
composer require thelia/google-tag-manager-module:~2.1.0
```

## Usage

You need to configure the id from your Google tag manager account in the thelia administration panel.\
It should look like ```GTM-XXXX```.

This module renders its scripts through Twig functions you place in your front theme.

| Function | Where | What it outputs |
|----------|-------|-----------------|
| `{{ gtm_head() }}` | in `<head>` | GTM container script + dataLayer pushes (page view, view_item, purchase…) |
| `{{ gtm_body() }}` | right after `<body>` | GTM `<noscript>` fallback |
| `{{ gtm_js_init() }}` | before `</body>` | `select_item` / `add_to_cart` event listeners |
| `{{ gtm_track_product(product.id) }}` | product page template | registers the viewed product (enables the `view_item` event) |

### Adding the functions to your front template

In your base layout (e.g. `templates/frontOffice/<your-theme>/base.html.twig`):

```twig
<head>
    {{ gtm_head() }}
    {# ... #}
</head>
<body>
    {{ gtm_body() }}
    {# ... #}
    {{ gtm_js_init() }}
</body>
```

In your product page template (e.g. `templates/frontOffice/<your-theme>/product.html.twig`),
inside the body — this is required for the `view_item` event:

```twig
{{ gtm_track_product(product.id) }}
```

To track products added to the cart, you need to implement this js event on the "Add to cart" buttons.
```js 
const event = new CustomEvent("addPseToCart", {
    detail: {
          pse: pseId,
          quantity
    },
 });
 document.dispatchEvent(event);
```
