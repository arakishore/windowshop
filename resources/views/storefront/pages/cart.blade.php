@extends('storefront.layouts.app')

@section('title', 'Shopping Cart | WindowShop')
@section('meta_description', 'Review your selected local shop products before checkout on WindowShop.')

@push('styles')
    <style>
        #cartRemoveConfirmModal .modal-dialog {
            max-width: 420px;
        }

        #cartRemoveConfirmModal .modal-content {
            padding: 26px 28px 24px;
        }

        #cartRemoveConfirmModal .icon-close-popup {
            width: 32px;
            height: 32px;
            top: 18px;
            right: 18px;
            font-size: 14px;
        }

        #cartRemoveConfirmModal .modal-heading {
            margin-bottom: 22px;
            padding-right: 22px;
        }

        #cartRemoveConfirmModal .title-pop {
            font-size: 26px;
            line-height: 32px;
            margin-bottom: 8px;
        }

        #cartRemoveConfirmModal .desc-pop {
            font-size: 15px;
            line-height: 22px;
        }

        #cartRemoveConfirmModal .modal-main {
            padding: 0;
        }

        #cartRemoveConfirmModal .cart-remove-confirm-actions {
            gap: 12px;
        }

        #cartRemoveConfirmModal .tf-btn {
            min-width: 118px;
            height: 38px;
            padding: 0 24px;
            font-size: 15px;
            line-height: 20px;
        }

        @media (max-width: 575px) {
            #cartRemoveConfirmModal .modal-dialog {
                max-width: none;
            }

            #cartRemoveConfirmModal .modal-content {
                padding: 24px 20px 22px;
            }

            #cartRemoveConfirmModal .title-pop {
                font-size: 24px;
                line-height: 30px;
            }
        }
    </style>
@endpush

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <h1>Shopping Cart</h1>

                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Shopping Cart</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-shoping-cart each-list-prd flat-spacing-2 pb-0" data-cart-page>
        <div class="flat-spacing-2 pt-0">
            <div class="container">
                <div class="tf-cart-notification">
                    <div class="count-text">
                        <div class="ic">
                            <i class="icon icon-Timer"></i>
                        </div>
                        <div>
                            Your cart is saved for now. Review store availability before checkout.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div data-cart-empty {{ $cart['is_empty'] ? '' : 'hidden' }}>
                <div class="text-center py-5">
                    <h4 class="mb-12">Your cart is empty</h4>
                    <p class="cl-text-2 mb-24">Add products from local shops to review them here.</p>
                    <a href="{{ route('storefront.products') }}" class="tf-btn animate-btn">
                        <span class="fw-semibold">Continue Shopping</span>
                    </a>
                </div>
            </div>

            <div class="row" data-cart-filled {{ $cart['is_empty'] ? 'hidden' : '' }}>
                <div class="col-lg-8">
                    <form class="form-shop-cart" data-cart-form>
                        <div class="overflow-auto">
                            <table class="tf-table-page-cart">
                                <thead>
                                    <tr>
                                        <th>
                                            <p class="h6 fw-medium">Products</p>
                                        </th>
                                        <th>
                                            <p class="h6 fw-medium">Price</p>
                                        </th>
                                        <th>
                                            <p class="h6 fw-medium">Quantity</p>
                                        </th>
                                        <th class="text-end">
                                            <p class="h6 fw-medium">Total Price</p>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody data-cart-items>
                                    @foreach ($cart['shop_groups'] as $shopGroup)
                                        <tr class="cart-shop-row" data-cart-shop="{{ $shopGroup['shop_id'] }}">
                                            <td colspan="4">
                                                <div class="d-flex justify-content-between align-items-center py-3">
                                                    <h6 class="mb-0">{{ $shopGroup['shop_name'] }}</h6>
                                                    <span class="text-caption-01 cl-text-2">
                                                        Shop subtotal:
                                                        <span data-shop-subtotal="{{ $shopGroup['shop_id'] }}">{{ $shopGroup['subtotal'] }}</span>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        @foreach ($shopGroup['items'] as $item)
                                            <tr class="tf-cart_item each-prd file-delete {{ $item['is_available'] ? '' : 'is-unavailable' }}"
                                                data-cart-item="{{ $item['id'] }}"
                                                data-cart-shop="{{ $shopGroup['shop_id'] }}">
                                                <td class="cart_product">
                                                    <a href="{{ $item['product_url'] }}" class="img-prd">
                                                        <img loading="lazy" width="100" height="133"
                                                            src="{{ $item['image'] }}"
                                                            alt="{{ $item['product_name'] }}">
                                                    </a>
                                                    <div class="infor-prd">
                                                        <a href="{{ $item['product_url'] }}"
                                                            class="prd_name fw-medium link lh-24">
                                                            {{ $item['product_name'] }}
                                                        </a>
                                                        @foreach ($item['attributes'] as $attribute)
                                                            <div class="prd_select text-caption-01">
                                                                <span class="type-text cl-text-3">{{ $attribute['label'] }}:&nbsp;</span>
                                                                <span class="fw-medium">{{ $attribute['value'] }}</span>
                                                            </div>
                                                        @endforeach
                                                        @if (! $item['is_available'])
                                                            <p class="text-caption-01 text-danger mb-0" data-cart-item-warning>
                                                                {{ $item['availability_message'] ?: 'Currently unavailable.' }}
                                                            </p>
                                                        @endif
                                                        <button type="button"
                                                            class="cart_remove tf-btn-line-3 type-primary remove"
                                                            data-cart-remove-url="{{ $item['remove_url'] }}">
                                                            <span class="text-caption-01 fw-semibold">Remove</span>
                                                        </button>
                                                        <p class="text-caption-01 mt-2 mb-0" data-cart-item-message role="status" hidden></p>
                                                    </div>
                                                </td>
                                                <td class="cart_price fw-semibold text-primary" data-cart-title="Price">
                                                    <span data-cart-item-price>{{ $item['unit_price'] }}</span>
                                                </td>
                                                <td class="cart_quantity" data-cart-title="Quantity">
                                                    <div class="wg-quantity">
                                                        <button type="button"
                                                            class="btn-quantity minus-quantity"
                                                            data-cart-quantity-step="-1"
                                                            {{ $item['is_available'] ? '' : 'disabled' }}>
                                                            <i class="icon icon-minus"></i>
                                                        </button>
                                                        <input class="quantity-product" type="text" name="quantity"
                                                            value="{{ $item['quantity'] }}"
                                                            inputmode="numeric"
                                                            data-cart-quantity-input
                                                            data-cart-update-url="{{ $item['update_url'] }}"
                                                            {{ $item['is_available'] ? '' : 'disabled' }}>
                                                        <button type="button"
                                                            class="btn-quantity plus-quantity"
                                                            data-cart-quantity-step="1"
                                                            {{ $item['is_available'] ? '' : 'disabled' }}>
                                                            <i class="icon icon-plus"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="cart_total fw-semibold text-primary" data-cart-item-subtotal>
                                                        {{ $item['line_subtotal'] }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="fl-sidebar-cart mt-lg-0 sticky-top">
                        <div class="box-order-summary">
                            <h5 class="title mb-20">Order Summary</h5>
                            <div class="subtotal d-flex justify-content-between align-items-center">
                                <p class="fw-medium lh-24">Sub-total</p>
                                <span class="total fw-medium lh-24" data-cart-subtotal>{{ $cart['subtotal'] }}</span>
                            </div>

                            <h5 class="total-order d-flex justify-content-between align-items-center">
                                <span>Total</span>
                                <span class="total" data-cart-total>{{ $cart['total'] }}</span>
                            </h5>

                            <div class="list-ver text-center">
                                <a href="{{ route('storefront.checkout') }}"
                                    class="action-checkout tf-btn w-100 animate-btn">
                                    <span class="fw-semibold">Proceed To Checkout</span>
                                </a>
                                <a href="{{ route('storefront.products') }}" class="link-underline link">
                                    <span class="fw-semibold">Or Continue Shopping</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal modalCentered fade" id="cartRemoveConfirmModal" tabindex="-1" aria-labelledby="cartRemoveConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <span class="icon-close-popup" data-bs-dismiss="modal" aria-label="Close">
                    <i class="icon-X2"></i>
                </span>
                <div class="modal-heading text-center">
                    <h4 id="cartRemoveConfirmTitle" class="title-pop mb-8">Remove item?</h4>
                    <p class="desc-pop cl-text-2 mb-0">Are you sure you want to remove this item from your cart?</p>
                </div>
                <div class="modal-main">
                    <div class="cart-remove-confirm-actions d-flex justify-content-center">
                        <button type="button" class="tf-btn btn-stroke" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="tf-btn animate-btn" data-cart-confirm-remove>Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const page = document.querySelector('[data-cart-page]');

            if (!page || !window.fetch) {
                return;
            }

            const csrfToken = () => {
                const metaToken = document.querySelector('meta[name="csrf-token"]');

                return metaToken ? metaToken.getAttribute('content') : '';
            };

            const showMessage = (row, text, type) => {
                const message = row?.querySelector('[data-cart-item-message]');

                if (!message) {
                    return;
                }

                message.textContent = text;
                message.hidden = false;
                message.classList.toggle('text-success', type === 'success');
                message.classList.toggle('text-danger', type !== 'success');
            };

            const syncCart = (payload) => {
                document.querySelectorAll('[data-storefront-cart-count]').forEach((count) => {
                    count.textContent = payload.cart_count || '0';
                });
                window.WindowShopMiniCart?.sync?.(payload);

                const subtotal = page.querySelector('[data-cart-subtotal]');
                const total = page.querySelector('[data-cart-total]');

                if (subtotal) {
                    subtotal.textContent = payload.subtotal || 'INR 0.00';
                }

                if (total) {
                    total.textContent = payload.total || 'INR 0.00';
                }

                if (payload.is_empty) {
                    page.querySelector('[data-cart-filled]')?.setAttribute('hidden', '');
                    page.querySelector('[data-cart-empty]')?.removeAttribute('hidden');
                }

                (payload.shop_groups || []).forEach((shop) => {
                    const shopSubtotal = page.querySelector(`[data-shop-subtotal="${shop.shop_id}"]`);

                    if (shopSubtotal) {
                        shopSubtotal.textContent = shop.subtotal;
                    }

                    (shop.items || []).forEach((item) => {
                        const row = page.querySelector(`[data-cart-item="${item.id}"]`);

                        if (!row) {
                            return;
                        }

                        const input = row.querySelector('[data-cart-quantity-input]');
                        const price = row.querySelector('[data-cart-item-price]');
                        const line = row.querySelector('[data-cart-item-subtotal]');

                        if (input) {
                            input.value = item.quantity;
                        }

                        if (price) {
                            price.textContent = item.unit_price;
                        }

                        if (line) {
                            line.textContent = item.line_subtotal;
                        }
                    });
                });
            };

            const requestCart = async (url, method, body = null) => {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body,
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = Object.values(data.errors || {}).flat()[0];
                    throw new Error(firstError || data.message || 'Cart could not be updated.');
                }

                return data;
            };

            const confirmModalElement = document.getElementById('cartRemoveConfirmModal');
            const confirmRemoveButton = confirmModalElement?.querySelector('[data-cart-confirm-remove]');
            const confirmModal = confirmModalElement && window.bootstrap
                ? window.bootstrap.Modal.getOrCreateInstance(confirmModalElement)
                : null;
            let pendingRemove = null;

            const removeCartRow = (row) => {
                if (!row) {
                    return;
                }

                const shopId = row.dataset.cartShop;
                row.remove();

                if (shopId && !page.querySelector(`[data-cart-item][data-cart-shop="${shopId}"]`)) {
                    page.querySelector(`[data-cart-shop="${shopId}"].cart-shop-row`)?.remove();
                }
            };

            page.querySelectorAll('[data-cart-quantity-step]').forEach((button) => {
                button.addEventListener('click', async (event) => {
                    event.stopImmediatePropagation();

                    const row = button.closest('[data-cart-item]');
                    const input = row ? row.querySelector('[data-cart-quantity-input]') : null;

                    if (!input) {
                        return;
                    }

                    const current = Number.parseFloat(input.value || '1');
                    const step = Number.parseFloat(button.dataset.cartQuantityStep || '0');
                    const next = Math.max(1, current + step);

                    if (next === current && step < 0) {
                        return;
                    }

                    const body = new URLSearchParams();
                    body.append('quantity', String(next));

                    try {
                        button.disabled = true;
                        const payload = await requestCart(input.dataset.cartUpdateUrl, 'PATCH', body);
                        syncCart(payload);
                        showMessage(row, payload.message || 'Cart updated.', 'success');
                    } catch (error) {
                        showMessage(row, error.message, 'error');
                    } finally {
                        button.disabled = false;
                    }
                }, true);
            });

            page.querySelectorAll('[data-cart-quantity-input]').forEach((input) => {
                input.addEventListener('change', async () => {
                    const row = input.closest('[data-cart-item]');
                    const body = new URLSearchParams();
                    body.append('quantity', input.value);

                    try {
                        const payload = await requestCart(input.dataset.cartUpdateUrl, 'PATCH', body);
                        syncCart(payload);
                        showMessage(row, payload.message || 'Cart updated.', 'success');
                    } catch (error) {
                        showMessage(row, error.message, 'error');
                    }
                });
            });

            page.querySelectorAll('[data-cart-remove-url]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    const row = button.closest('[data-cart-item]');
                    const url = button.dataset.cartRemoveUrl;

                    if (!row || !url) {
                        return;
                    }

                    pendingRemove = { button, row, url };
                    confirmModal?.show();
                }, true);
            });

            confirmRemoveButton?.addEventListener('click', async () => {
                if (!pendingRemove) {
                    return;
                }

                const { button, row, url } = pendingRemove;

                try {
                    button.disabled = true;
                    confirmRemoveButton.disabled = true;
                    const payload = await requestCart(url, 'DELETE');
                    removeCartRow(row);
                    syncCart(payload);
                    confirmModal?.hide();
                    pendingRemove = null;
                } catch (error) {
                    showMessage(row, error.message, 'error');
                } finally {
                    button.disabled = false;
                    confirmRemoveButton.disabled = false;
                }
            });

            confirmModalElement?.addEventListener('hidden.bs.modal', () => {
                pendingRemove = null;
            });
        });
    </script>
@endpush
