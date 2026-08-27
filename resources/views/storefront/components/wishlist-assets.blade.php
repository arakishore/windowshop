@once
    @push('styles')
        <style>
            [data-wishlist-toggle].is-wishlisted {
                color: #ffffff;
                background: var(--primary);
            }

            [data-wishlist-toggle].is-loading {
                opacity: .72;
                pointer-events: none;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                const selector = '[data-wishlist-toggle]';
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                const setState = (button, wishlisted) => {
                    const label = wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist';

                    button.classList.toggle('is-wishlisted', wishlisted);
                    button.dataset.wishlistState = wishlisted ? '1' : '0';
                    button.setAttribute('title', label);
                    button.setAttribute('aria-label', label);

                    const tooltip = button.querySelector('.tooltip');

                    if (tooltip) {
                        tooltip.textContent = label;
                    }

                    const visibleLabel = button.querySelector('[data-wishlist-label]');

                    if (visibleLabel) {
                        visibleLabel.textContent = wishlisted ? 'Remove' : 'Add to Wishlist';
                    }
                };

                const setProductState = (productId, wishlisted) => {
                    document.querySelectorAll(`${selector}[data-wishlist-product-id="${productId}"]`)
                        .forEach((button) => setState(button, wishlisted));

                    if (!wishlisted) {
                        document.querySelectorAll(`[data-wishlist-card="${productId}"]`).forEach((card) => card.remove());

                        const grid = document.querySelector('[data-wishlist-grid]');
                        const empty = document.querySelector('[data-wishlist-empty]');

                        if (grid && empty && grid.querySelectorAll('[data-wishlist-card]').length === 0) {
                            empty.classList.remove('d-none');
                        }
                    }
                };

                document.addEventListener('click', async (event) => {
                    const button = event.target.closest(selector);

                    if (!button) {
                        return;
                    }

                    event.preventDefault();

                    const isWishlisted = button.dataset.wishlistState === '1';
                    const url = isWishlisted ? button.dataset.wishlistDestroyUrl : button.dataset.wishlistStoreUrl;

                    if (!url) {
                        return;
                    }

                    button.classList.add('is-loading');

                    try {
                        const response = await fetch(url, {
                            method: isWishlisted ? 'DELETE' : 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (response.status === 401) {
                            let payload = {};

                            try {
                                payload = await response.json();
                            } catch (error) {
                                payload = {};
                            }

                            window.location.href = payload.login_url || button.dataset.loginUrl;
                            return;
                        }

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        setProductState(String(payload.product_id || button.dataset.wishlistProductId), !!payload.wishlisted);
                    } finally {
                        button.classList.remove('is-loading');
                    }
                });
            })();
        </script>
    @endpush
@endonce
