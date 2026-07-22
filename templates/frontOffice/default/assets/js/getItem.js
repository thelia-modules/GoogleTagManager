document.addEventListener('DOMContentLoaded', async () => {
    const products = document.querySelectorAll("a.ProductCard, .ProductCard a");

    if (!window.dataLayer) return;

    products.forEach(product => {
        product.addEventListener('click',(e) => processLinkClick(e), false);
    })
})

async function processLinkClick (e) {
    e.preventDefault();

    const targetUrl = e.currentTarget?.href ?? e.currentTarget.getElementsByTagName('a')[0].href;

    if (!targetUrl) return null;

    const response = await fetch(window.GTM_GET_ITEM_URL, {
        method: "POST",
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ productUrl: targetUrl })
    })

    const resultJson = await response.json();

    window.dataLayer.push({ ecommerce: null });

    window.dataLayer.push({
        'event': 'select_item',
        'ecommerce': { items: JSON.parse(resultJson) }
    });

    window.location.href = targetUrl;
}
