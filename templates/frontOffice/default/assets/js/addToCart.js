document.addEventListener('addPseToCart', addPseToCart);
document.addEventListener('removePseFromCart', removePseFromCart);

async function getCartItem(e) {
    const response = await fetch(window.GTM_ADD_TO_CART, {
        method: "POST",
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ pseId: e.detail.pse, quantity: e.detail.quantity })
    })
    return await response.json();
}

async function addPseToCart(e) {
    const resultJson = await getCartItem(e);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event: 'add_to_cart', ecommerce: JSON.parse(resultJson) });
}

async function removePseFromCart(e) {
    const resultJson = await getCartItem(e);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event: 'remove_from_cart', ecommerce: JSON.parse(resultJson) });
}




