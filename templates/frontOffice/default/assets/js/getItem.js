const TRACKED_LINK_SELECTOR = 'a.ProductCard, .ProductCard a';

// Bubbling on the document, so a link's own handlers run first and defaultPrevented can be trusted.
document.addEventListener('click', onLinkActivation, false);
document.addEventListener('auxclick', onLinkActivation, false);

function onLinkActivation(event) {
    if (!window.dataLayer) {
        return;
    }

    // The secondary button opens a context menu, it does not activate the link.
    if ('auxclick' === event.type && 1 !== event.button) {
        return;
    }

    if (event.defaultPrevented) {
        return;
    }

    const link = event.target?.closest?.('a[href]');

    if (!link?.matches(TRACKED_LINK_SELECTOR)) {
        return;
    }

    const targetUrl = link.href;

    // The link opens elsewhere: the current page survives, so there is nothing to hold back.
    if (opensAwayFromCurrentPage(event, link)) {
        trackSelectItem(targetUrl);

        return;
    }

    event.preventDefault();

    // The click must always navigate: a slow, failing or unreachable tracking endpoint
    // cannot be allowed to swallow it.
    trackSelectItem(targetUrl).finally(() => {
        window.location.href = targetUrl;
    });
}

function opensAwayFromCurrentPage(event, link) {
    return 'auxclick' === event.type
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
        || ('' !== link.target && '_self' !== link.target);
}

async function trackSelectItem(productUrl) {
    try {
        const response = await fetch(window.GTM_GET_ITEM_URL, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ productUrl }),
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
    }
}
