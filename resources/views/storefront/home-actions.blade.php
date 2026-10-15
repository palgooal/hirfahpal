<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-homepage]');
    if (!page) return;

    const csrf = @json(csrf_token());
    const currency = @json($currency);
    const loginUrl = @json(route('customer.login'));
    const cartUrl = @json(route('customer.cart.items.store'));
    const favoritesUrl = @json(route('customer.favorites.store'));
    const favoriteDeleteUrl = @json(url('/customer/favorites'));
    const signedIn = @json(auth('customer')->check());
    const favoriteIds = new Map(Object.entries(@json((object) $favoriteIds)));

    function renderCart(count, total) {
        document.getElementById('cartCount').textContent = count;
        document.getElementById('cartTotal').textContent = Number(total).toFixed(2) + ' ' + currency;
    }
    renderCart(@json($cartCount), @json($cartTotal));
    const cartButton = document.getElementById('cartButton');
    cartButton.disabled = false;
    cartButton.removeAttribute('aria-disabled');
    cartButton.addEventListener('click', () => window.location.assign(@json(route('cart'))));

    async function send(url, method, data) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'تعذر تنفيذ الطلب، حاول مرة أخرى.');
        }
        return payload;
    }

    page.querySelectorAll('.add-cart, .favorite').forEach(button => {
        button.addEventListener('click', async () => {
            const productId = button.closest('[data-product-id]').dataset.productId;
            const isFavorite = button.classList.contains('favorite');
            if (isFavorite && !signedIn) {
                window.location.assign(loginUrl);
                return;
            }
            button.disabled = true;
            try {
                if (isFavorite) {
                    const existingId = favoriteIds.get(productId);
                    const result = await send(existingId ? favoriteDeleteUrl + '/' + existingId : favoritesUrl,
                        existingId ? 'DELETE' : 'POST', { product_id: Number(productId) });
                    if (existingId) favoriteIds.delete(productId);
                    else favoriteIds.set(productId, result.id);
                    page.querySelectorAll('[data-product-id="' + productId + '"] .favorite').forEach(control => {
                        control.setAttribute('aria-pressed', String(!existingId));
                        control.title = existingId ? 'تمت الإزالة من المفضلة' : 'تمت الإضافة للمفضلة';
                    });
                } else {
                    const cart = await send(cartUrl, 'POST', { product_id: Number(productId), quantity: 1 });
                    renderCart(cart.items.reduce((sum, item) => sum + item.quantity, 0),
                        cart.items.reduce((sum, item) => sum + item.quantity * Number(item.product?.price ?? item.unit_price), 0));
                    button.title = 'تمت الإضافة إلى السلة';
                    const label = button.querySelector('span');
                    if (label) {
                        const previous = label.textContent;
                        label.textContent = 'تمت الإضافة';
                        setTimeout(() => label.textContent = previous, 1600);
                    }
                }
            } catch (error) {
                window.alert(error.message);
            } finally {
                button.disabled = false;
            }
        });
    });

    const newsletter = document.getElementById('newsletter');
    newsletter.addEventListener('submit', async event => {
        event.preventDefault();
        const button = newsletter.querySelector('button');
        const status = document.getElementById('newsletterStatus');
        status.hidden = false;
        button.disabled = true;
        try {
            const result = await send(newsletter.action, 'POST', { email: newsletter.elements.email.value });
            status.textContent = result.message;
            newsletter.reset();
        } catch (error) {
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
});
</script>
