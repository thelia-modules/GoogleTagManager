document.addEventListener('DOMContentLoaded', () => {
    if (!window.dataLayer) return;

    document.querySelectorAll('a.ProductCard, .ProductCard a').forEach(product => {
        product.addEventListener('click', processLinkClick, false);
    });
});

async function processLinkClick(e) {
    const targetUrl = e.currentTarget?.href ?? e.currentTarget.getElementsByTagName('a')[0]?.href;

    // Nothing to track and nothing to restore: let the browser handle the click.
    if (!targetUrl) return;

    e.preventDefault();

    // The click must always navigate: a slow, failing or unreachable tracking endpoint
    // cannot be allowed to swallow it.
    try {
        const response = await fetch(window.GTM_GET_ITEM_URL, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ productUrl: targetUrl }),
            signal: AbortSignal.timeout(2000)
        });

        if (response.ok) {
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                event: 'select_item',
                ecommerce: { items: JSON.parse(await response.json()) }
            });
        }
    } catch (error) {
        console.error('GTM select_item tracking failed', error);
    } finally {
        window.location.href = targetUrl;
    }
}
