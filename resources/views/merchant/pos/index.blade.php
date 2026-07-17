{{-- Purpose: Merchant POS screen for fast active-shop cash sales. --}}
@extends('layouts.merchant')

@section('title', 'Merchant POS | WindowShop')

@push('styles')
    <style>
        .pos-shell {
            --pos-cart-width: 390px;
            --pos-workspace-height: calc(100vh - 3.5rem);
        }

        .pos-toolbar {
            position: sticky;
            top: 0;
            z-index: 4;
            background: var(--body-bg, #f5f7fb);
            padding-bottom: .75rem;
        }

        .pos-search-control {
            min-height: 3rem;
            border-radius: .75rem;
        }

        .pos-search-typeahead {
            position: relative;
            min-width: 0;
        }

        .pos-search-typeahead .pos-search-control {
            position: relative;
            z-index: 2;
            width: 100%;
            background: transparent;
            box-shadow: none;
        }

        .pos-search-ghost {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            overflow: hidden;
            padding: .5rem 1rem;
            color: #9ca3af;
            pointer-events: none;
            white-space: nowrap;
        }

        .pos-search-ghost-prefix {
            color: transparent;
        }

        .pos-scan-notice {
            position: fixed;
            top: 1rem;
            left: 50%;
            z-index: 1080;
            min-width: min(28rem, calc(100vw - 2rem));
            transform: translateX(-50%);
            box-shadow: 0 .75rem 2rem rgba(15, 23, 42, .18);
        }

        .pos-shop-select {
            min-width: min(22rem, 70vw);
        }

        .pos-category-scroll {
            display: flex;
            gap: .5rem;
            overflow-x: auto;
            padding-bottom: .25rem;
            scrollbar-width: thin;
        }

        .pos-category-scroll .btn {
            white-space: nowrap;
            border-radius: 999px;
        }

        .pos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
        }

        .pos-product-card {
            display: flex;
            flex-direction: column;
            min-height: 215px;
            border: 1px solid var(--border-color, #ddd);
            border-radius: .75rem;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .pos-product-card[role="button"] {
            cursor: pointer;
        }

        .pos-product-card:hover {
            border-color: rgba(var(--primary-rgb, 13, 110, 253), .35);
            box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .07);
            transform: translateY(-1px);
        }

        .pos-product-image {
            height: 66px;
            margin: .75rem .75rem 0;
            border-radius: .6rem;
            background: #f7f8fa;
            overflow: hidden;
        }

        .pos-product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .pos-product-image-placeholder {
            height: 100%;
            display: grid;
            place-items: center;
            color: #9ca3af;
            font-size: 2rem;
        }

        .pos-product-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: .35rem;
            padding: .85rem;
        }

        .pos-product-name {
            min-height: 2.45rem;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .pos-product-variant {
            min-height: 1.1rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pos-cart-panel {
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color, #ddd);
            border-radius: .85rem;
            background: #fff;
            padding: 1rem;
            height: var(--pos-workspace-height);
            overflow: hidden;
        }

        .pos-cart-items {
            flex: 1 1 auto;
            min-height: 56px;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding-right: .25rem;
        }

        .pos-empty-cart {
            padding-top: 1.25rem !important;
            padding-bottom: 1.25rem !important;
        }

        .pos-cart-footer {
            flex: 0 0 auto;
            background: #fff;
        }

        .pos-cart-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: .75rem;
            padding: .75rem 0;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
        }

        .pos-cart-row:last-child {
            border-bottom: 0;
        }

        .pos-qty-control {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--border-color, #ddd);
            border-radius: 999px;
            overflow: hidden;
        }

        .pos-qty-control button {
            border: 0;
            width: 2rem;
            height: 2rem;
            background: transparent;
        }

        .pos-qty-control span {
            min-width: 2rem;
            text-align: center;
            font-weight: 700;
        }

        .pos-grand-total {
            font-size: 1.45rem;
        }

        .pos-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: .75rem;
        }

        .pos-quick-actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .5rem;
        }

        .pos-quick-actions .btn {
            min-width: 0;
            padding-left: .4rem;
            padding-right: .4rem;
        }

        .pos-actions .js-pos-complete {
            min-height: 2.75rem;
        }

        @media (min-width: 1200px) {
            .pos-content {
                display: grid;
                grid-template-columns: minmax(0, 1fr) var(--pos-cart-width);
                gap: 1rem;
                align-items: start;
                height: var(--pos-workspace-height);
                overflow: hidden;
            }

            .pos-main-column {
                display: flex;
                flex-direction: column;
                height: var(--pos-workspace-height);
                min-width: 0;
                overflow: hidden;
            }

            .pos-main-column main {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                padding-right: .25rem;
            }
        }

        @media (max-width: 767.98px) {
            .pos-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .75rem;
            }

            .pos-product-card {
                min-height: 205px;
            }

            .pos-product-image {
                height: 56px;
            }

            .pos-cart-panel {
                position: static;
                height: auto;
                overflow: visible;
                margin-top: 1rem;
            }

            .pos-cart-items {
                max-height: 320px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $activeCategoryId = (int) $filters['category_id'];
        $shopContext = $merchantActiveShopContext ?? [
            'shops' => collect(),
            'activeShop' => $activeShop ?? null,
            'activeShopLabel' => session('active_shop_name', 'No active shop'),
        ];
        $activeShops = $shopContext['shops'];
        $selectedShop = $shopContext['activeShop'];
        $shopLabel = $shopContext['activeShopLabel'];
    @endphp

    <div
        class="pos-shell js-pos-root"
        data-items='@json($posItems->keyBy('id')->all())'
        data-checkout-url="{{ route('merchant.pos.checkout') }}"
        data-search-url="{{ route('merchant.pos.search') }}"
        data-recent-sales-url="{{ route('merchant.pos.recent-sales') }}"
        data-shop-id="{{ $selectedShop?->getKey() ?? $activeShop?->getKey() }}"
    >
        <div class="pos-content">
            <div class="pos-main-column">
                <div class="pos-toolbar">
                    <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center mb-3">
                        <div class="flex-fill">
                            <form method="GET" action="{{ route('merchant.pos.index') }}">
                                @if($activeCategoryId > 0)
                                    <input type="hidden" name="category_id" value="{{ $activeCategoryId }}">
                                @endif
                                <div class="input-group input-group-lg">
                                    <a href="{{ route('merchant.dashboard') }}" class="btn btn-light" title="Dashboard" aria-label="Dashboard">
                                        <i class="ph-house"></i>
                                    </a>
                                    <span class="input-group-text bg-white"><i class="ph-magnifying-glass"></i></span>
                                    <div class="form-control p-0 pos-search-typeahead">
                                        <div class="pos-search-ghost js-pos-search-ghost" aria-hidden="true"></div>
                                        <input
                                            type="search"
                                            name="search"
                                            value="{{ $filters['search'] }}"
                                            class="form-control pos-search-control border-0"
                                            placeholder="Search product name, SKU, or scan barcode"
                                            aria-label="Search products, SKU or scan barcode"
                                            autocomplete="off"
                                            inputmode="search"
                                            autofocus
                                        >
                                    </div>
                                    <button type="button" class="btn btn-light js-pos-search-clear {{ $filters['search'] === '' ? 'd-none' : '' }}" title="Clear search">
                                        <i class="ph-x"></i>
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                            <span class="text-muted fs-sm text-nowrap">POS for</span>
                            @if ($activeShops->count() > 1)
                                <form method="POST" action="{{ route('merchant.active-shop.update') }}" class="mb-0">
                                    @csrf
                                    <select name="shop_id" class="form-select pos-shop-select" onchange="this.form.submit()" aria-label="Select POS shop">
                                        @foreach ($activeShops as $shop)
                                            <option value="{{ $shop->getKey() }}" @selected($selectedShop?->is($shop))>
                                                {{ $shop->name }}{{ $shop->city?->name ? ' - '.$shop->city->name : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3">{{ $shopLabel }}</span>
                            @endif
                            <span class="badge bg-success bg-opacity-10 text-success py-2 px-3">Online</span>
                            <div class="dropdown">
                                <button type="button" class="btn btn-light btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="POS actions">
                                    <i class="ph-dots-three"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <button type="button" class="dropdown-item js-pos-clear">
                                        <i class="ph-trash me-2"></i>
                                        Clear cart
                                    </button>
                                    <button type="button" class="dropdown-item js-pos-reprint-last" disabled>
                                        <i class="ph-printer me-2"></i>
                                        Reprint last receipt
                                    </button>
                                    <button type="button" class="dropdown-item js-pos-held-orders">
                                        <i class="ph-receipt me-2"></i>
                                        Held orders
                                        <span class="badge bg-primary ms-auto js-pos-held-count">0</span>
                                    </button>
                                    <button type="button" class="dropdown-item js-pos-recent-sales">
                                        <i class="ph-clock-counter-clockwise me-2"></i>
                                        Recent sales
                                    </button>
                                    <button type="button" class="dropdown-item js-pos-shortcuts">
                                        <i class="ph-key me-2"></i>
                                        Keyboard shortcuts
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pos-category-scroll">
                        <a href="{{ route('merchant.pos.index', $filters['search'] !== '' ? ['search' => $filters['search']] : []) }}"
                           class="btn {{ $activeCategoryId === 0 ? 'btn-primary' : 'btn-light' }}">
                            All
                            <span class="badge {{ $activeCategoryId === 0 ? 'bg-white text-primary' : 'bg-secondary bg-opacity-10 text-body' }} ms-1">{{ $posItems->count() }}</span>
                        </a>
                        @foreach($categories as $category)
                            @php
                                $query = array_filter([
                                    'category_id' => $category->getKey(),
                                    'search' => $filters['search'] !== '' ? $filters['search'] : null,
                                ]);
                            @endphp
                            <a href="{{ route('merchant.pos.index', $query) }}"
                               class="btn {{ $activeCategoryId === (int) $category->getKey() ? 'btn-primary' : 'btn-light' }}">
                                {{ $category->name }}
                                <span class="badge {{ $activeCategoryId === (int) $category->getKey() ? 'bg-white text-primary' : 'bg-secondary bg-opacity-10 text-body' }} ms-1">{{ $category->products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <main>
                    @if($posItems->isEmpty())
                        <x-empty-state icon="ph-package" title="No POS products found" message="Active products with active variants will appear here." />
                    @else
                        <div class="pos-grid">
                            @foreach($posItems as $item)
                                @include('merchant.pos.partials.product-card', ['item' => $item])
                            @endforeach
                        </div>
                        <div class="js-pos-search-empty d-none">
                            <x-empty-state icon="ph-magnifying-glass" title="No matching products" message="Try another product name, SKU, or barcode." />
                        </div>
                    @endif
                </main>
            </div>

            @include('merchant.pos.partials.cart-panel')
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.querySelector('.js-pos-root');
            if (!root) {
                return;
            }

            const money = new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                currencyDisplay: 'code',
            });
            const products = JSON.parse(root.dataset.items || '{}');
            const cart = new Map();
            const storageKey = `windowshop.pos.cart.${root.dataset.shopId || 'default'}`;
            const heldStorageKey = `windowshop.pos.held.${root.dataset.shopId || 'default'}`;
            const lastReceiptStorageKey = `windowshop.pos.lastReceipt.${root.dataset.shopId || 'default'}`;
            const cartItems = root.querySelector('.js-pos-cart-items');
            const subtotalEl = root.querySelector('.js-pos-subtotal');
            const grandTotalEl = root.querySelector('.js-pos-grand-total');
            const elapsedTimeEl = root.querySelector('.js-pos-elapsed-time');
            const searchInput = root.querySelector('.pos-search-control');
            const searchForm = searchInput?.closest('form');
            const searchGhostEl = root.querySelector('.js-pos-search-ghost');
            const searchClearButton = root.querySelector('.js-pos-search-clear');
            const productCards = Array.from(root.querySelectorAll('.js-pos-product-card'));
            const searchEmptyEl = root.querySelector('.js-pos-search-empty');
            const cashInput = root.querySelector('.js-pos-cash-received');
            const paymentMethodInput = root.querySelector('.js-pos-payment-method');
            const paidLabelEl = root.querySelector('.js-pos-paid-label');
            const changeEl = root.querySelector('.js-pos-change');
            const completeButton = root.querySelector('.js-pos-complete');
            const reprintLastButtons = Array.from(root.querySelectorAll('.js-pos-reprint-last'));
            const heldCountEls = Array.from(root.querySelectorAll('.js-pos-held-count'));
            const heldListEl = root.querySelector('.js-pos-held-list');
            const heldEmptyEl = root.querySelector('.js-pos-held-empty');
            const heldModalEl = document.getElementById('posHeldOrdersModal');
            const recentSalesModalEl = document.getElementById('posRecentSalesModal');
            const recentSalesLoadingEl = document.querySelector('.js-pos-recent-loading');
            const recentSalesEmptyEl = document.querySelector('.js-pos-recent-empty');
            const recentSalesListEl = document.querySelector('.js-pos-recent-list');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let heldCarts = [];
            let timerStartedAt = null;
            let timerElapsedBeforeStart = 0;
            let timerInterval = null;
            const scanQueue = [];
            let scanLookupRunning = false;
            let searchKeyTimings = [];

            const cartTotal = () => Array.from(cart.values()).reduce((sum, row) => sum + (row.price * row.quantity), 0);
            const elapsedSeconds = () => timerElapsedBeforeStart + (timerStartedAt === null ? 0 : Math.max(0, Math.floor((Date.now() - timerStartedAt) / 1000)));
            const selectedFulfilment = () => root.querySelector('input[name="fulfilment_type"]:checked')?.value || 'counter';
            const selectedPaymentMethod = () => paymentMethodInput.value || 'cash';
            const isTypingTarget = (target) => ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName) || target?.isContentEditable;
            const normalizeSearch = (value) => String(value || '').trim().toLowerCase();
            const compactSearch = (value) => normalizeSearch(value).replace(/[^a-z0-9]/g, '');
            const isCodeSearch = (query) => /[0-9]/.test(query) && /^[a-z0-9\s._/-]+$/i.test(query);
            const searchKeywords = Array.from(productCards.reduce((keywords, card) => {
                normalizeSearch(card.dataset.search || '')
                    .split(/[^a-z0-9]+/)
                    .filter((word) => word.length > 2)
                    .forEach((word) => keywords.set(word, (keywords.get(word) || 0) + 1));

                return keywords;
            }, new Map()).entries())
                .sort((left, right) => right[1] - left[1] || left[0].localeCompare(right[0]))
                .map(([word]) => word);
            let currentSearchSuggestion = '';
            const setFulfilment = (value) => {
                const fulfilment = Array.from(root.querySelectorAll('input[name="fulfilment_type"]'))
                    .find((input) => input.value === (value || 'counter'));

                if (fulfilment) {
                    fulfilment.checked = true;
                }
            };
            const formatElapsed = (seconds) => {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const remainingSeconds = seconds % 60;
                const parts = [
                    String(minutes).padStart(2, '0'),
                    String(remainingSeconds).padStart(2, '0'),
                ];

                return hours > 0 ? `${hours}:${parts.join(':')}` : parts.join(':');
            };
            const renderTimer = () => {
                elapsedTimeEl.textContent = formatElapsed(elapsedSeconds());
            };
            const startTimer = () => {
                if (timerStartedAt !== null) {
                    return;
                }

                timerStartedAt = Date.now();
                renderTimer();
                timerInterval = window.setInterval(renderTimer, 1000);
            };
            const resumeTimer = (elapsedBeforeStart = 0, startedAt = Date.now()) => {
                if (timerInterval !== null) {
                    window.clearInterval(timerInterval);
                }

                timerElapsedBeforeStart = Math.max(0, Number(elapsedBeforeStart) || 0);
                timerStartedAt = startedAt;
                renderTimer();
                timerInterval = window.setInterval(renderTimer, 1000);
            };
            const pauseTimer = () => {
                timerElapsedBeforeStart = elapsedSeconds();
                if (timerInterval !== null) {
                    window.clearInterval(timerInterval);
                }

                timerStartedAt = null;
                timerInterval = null;
                renderTimer();
            };
            const resetTimer = () => {
                if (timerInterval !== null) {
                    window.clearInterval(timerInterval);
                }

                timerElapsedBeforeStart = 0;
                timerStartedAt = null;
                timerInterval = null;
                renderTimer();
            };
            const cartSnapshot = (overrides = {}) => ({
                id: overrides.id || `hold-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                label: overrides.label || '',
                items: Array.from(cart.values()),
                cashReceived: cashInput.value,
                paymentMethod: selectedPaymentMethod(),
                fulfilmentType: selectedFulfilment(),
                elapsedSeconds: elapsedSeconds(),
                timerStartedAt,
                timerElapsedBeforeStart,
                heldAt: overrides.heldAt || new Date().toISOString(),
            });
            const saveCart = () => {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(cartSnapshot({ id: 'active' })));
                } catch (error) {
                    // Storage can fail in private browsing or full disks; POS still works without persistence.
                }
            };
            const clearSavedCart = () => {
                try {
                    localStorage.removeItem(storageKey);
                } catch (error) {
                    // Ignore storage cleanup failures.
                }
            };
            const readSavedJson = (key, fallback) => {
                try {
                    return JSON.parse(localStorage.getItem(key) || JSON.stringify(fallback));
                } catch (error) {
                    return fallback;
                }
            };
            const lastReceipt = () => readSavedJson(lastReceiptStorageKey, null);
            const saveLastReceipt = (order) => {
                try {
                    localStorage.setItem(lastReceiptStorageKey, JSON.stringify({
                        number: order.number,
                        receiptUrl: order.receipt_url,
                        printUrl: order.print_url,
                    }));
                } catch (error) {
                    // Reprint is a convenience; checkout must not depend on browser storage.
                }
            };
            const renderLastReceiptButton = () => {
                reprintLastButtons.forEach((button) => {
                    button.disabled = !lastReceipt()?.printUrl;
                });
            };
            const writeHeldCarts = () => {
                try {
                    localStorage.setItem(heldStorageKey, JSON.stringify(heldCarts));
                } catch (error) {
                    // POS still works without held-cart persistence.
                }
            };
            const loadHeldCarts = () => {
                const saved = readSavedJson(heldStorageKey, []);
                heldCarts = Array.isArray(saved) ? saved : [];
                renderHeldCarts();
            };
            const restoreCart = () => {
                const saved = readSavedJson(storageKey, null);

                if (!saved || !Array.isArray(saved.items)) {
                    return;
                }

                loadSnapshot(saved);
            };
            const loadSnapshot = (snapshot) => {
                cart.clear();

                (snapshot.items || []).forEach((row) => {
                    const variantId = String(row.id || '');
                    const currentProduct = products[variantId];

                    if (variantId === '' || Number(row.quantity) < 1) {
                        return;
                    }

                    cart.set(variantId, {
                        ...(currentProduct || row),
                        id: variantId,
                        quantity: Number(row.quantity),
                    });
                });

                cashInput.value = snapshot.cashReceived || '';
                paymentMethodInput.value = snapshot.paymentMethod || 'cash';
                setFulfilment(snapshot.fulfilmentType || 'counter');
                renderPaymentMethod(false);

                if (cart.size > 0) {
                    if (Number(snapshot.timerStartedAt) > 0) {
                        resumeTimer(Number(snapshot.timerElapsedBeforeStart || 0), Number(snapshot.timerStartedAt));
                    } else {
                        resumeTimer(Number(snapshot.elapsedSeconds ?? snapshot.timerElapsedBeforeStart ?? 0), Date.now());
                    }
                } else {
                    resetTimer();
                }
            };

            const render = () => {
                if (cart.size === 0) {
                    if (timerStartedAt !== null || timerElapsedBeforeStart > 0) {
                        resetTimer();
                    }

                    cartItems.innerHTML = `
                        <div class="pos-empty-cart text-center text-muted py-5">
                            <i class="ph-shopping-cart-simple ph-2x d-block mb-2"></i>
                            Add products to start a sale.
                        </div>
                    `;
                } else {
                    cartItems.innerHTML = Array.from(cart.values()).map((row) => `
                        <div class="pos-cart-row" data-variant-id="${row.id}">
                            <div>
                                <div class="fw-semibold">${escapeHtml(row.product_name)}</div>
                                <div class="text-muted fs-sm">${escapeHtml(row.variant_name || 'Standard variant')}</div>
                                <div class="fw-semibold mt-1">${money.format(row.price)}</div>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-light btn-icon btn-sm rounded-pill js-pos-remove" title="Remove">
                                    <i class="ph-trash"></i>
                                </button>
                                <div class="pos-qty-control mt-2">
                                    <button type="button" class="js-pos-decrease" title="Decrease"><i class="ph-minus"></i></button>
                                    <span>${row.quantity}</span>
                                    <button type="button" class="js-pos-increase" title="Increase"><i class="ph-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }

                const total = cartTotal();
                const paid = Number.parseFloat(cashInput.value || '0');
                const isCash = selectedPaymentMethod() === 'cash';
                subtotalEl.textContent = money.format(total);
                grandTotalEl.textContent = money.format(total);
                renderTimer();
                changeEl.textContent = money.format(isCash ? Math.max(0, paid - total) : 0);
                completeButton.disabled = cart.size === 0 || (isCash && paid < total);
            };
            const renderPaymentMethod = (persist = true) => {
                const method = selectedPaymentMethod();
                paidLabelEl.textContent = method === 'cash' ? 'Cash Received' : 'Amount Paid';

                if (method !== 'cash') {
                    cashInput.value = cartTotal().toFixed(2);
                }

                render();
                if (persist) {
                    saveCart();
                }
            };
            const updateSearchSuggestion = () => {
                if (!searchInput || !searchGhostEl) {
                    return;
                }

                currentSearchSuggestion = '';
                searchGhostEl.textContent = '';

                const rawQuery = searchInput.value || '';
                const cursorAtEnd = searchInput.selectionStart === rawQuery.length && searchInput.selectionEnd === rawQuery.length;
                const match = rawQuery.match(/^(.*?)([a-z0-9]+)$/i);

                if (!cursorAtEnd || !match || match[2].length < 2) {
                    return;
                }

                const typedWord = compactSearch(match[2]);
                const suggestion = searchKeywords.find((word) => word.startsWith(typedWord) && word !== typedWord);

                if (!suggestion) {
                    return;
                }

                const suffix = suggestion.slice(typedWord.length);
                currentSearchSuggestion = `${rawQuery}${suffix}`;
                searchGhostEl.innerHTML = `<span class="pos-search-ghost-prefix">${escapeHtml(rawQuery)}</span><span>${escapeHtml(suffix)}</span>`;
            };
            const acceptSearchSuggestion = () => {
                if (!currentSearchSuggestion || !searchInput) {
                    return false;
                }

                searchInput.value = currentSearchSuggestion;
                filterProducts();
                return true;
            };
            const filterProducts = () => {
                const query = normalizeSearch(searchInput?.value);
                let visibleCount = 0;

                productCards.forEach((card) => {
                    const score = query === ''
                        ? 1
                        : (isCodeSearch(query) ? codeSearchScore(query, card) : searchScore(query, card.dataset.search || ''));
                    const isVisible = score > 0;
                    card.classList.toggle('d-none', !isVisible);
                    card.style.order = isVisible ? String(1000 - score) : '';
                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                searchClearButton?.classList.toggle('d-none', query === '');
                searchEmptyEl?.classList.toggle('d-none', visibleCount > 0);
                updateSearchSuggestion();
            };
            const codeSearchScore = (query, card) => {
                const compactQuery = compactSearch(query);
                const compactSku = compactSearch(card.dataset.sku || '');
                const compactBarcode = compactSearch(card.dataset.barcode || '');

                if (compactQuery === '') {
                    return 1;
                }

                if (compactBarcode === compactQuery) {
                    return 1000;
                }

                if (compactSku === compactQuery) {
                    return 980;
                }

                if (compactBarcode.startsWith(compactQuery)) {
                    return 820 - Math.min(compactBarcode.length - compactQuery.length, 200);
                }

                if (compactSku.startsWith(compactQuery)) {
                    return 800 - Math.min(compactSku.length - compactQuery.length, 200);
                }

                return 0;
            };
            const searchScore = (query, haystack) => {
                const normalizedHaystack = normalizeSearch(haystack);
                const compactQuery = compactSearch(query);
                const compactHaystack = compactSearch(haystack);

                if (compactQuery === '') {
                    return 1;
                }

                if (compactHaystack === compactQuery) {
                    return 1000;
                }

                if (normalizedHaystack.includes(query) || compactHaystack.includes(compactQuery)) {
                    return 850 - Math.min(compactHaystack.indexOf(compactQuery), 200);
                }

                const words = query.split(/\s+/).filter(Boolean);
                if (words.length > 1 && words.every((word) => compactHaystack.includes(compactSearch(word)))) {
                    return 720;
                }

                const typo = typoWordScore(words.length > 0 ? words : [query], normalizedHaystack);
                if (typo > 0) {
                    return typo;
                }

                const fuzzy = fuzzySequenceScore(compactQuery, compactHaystack);
                if (fuzzy > 0) {
                    return fuzzy;
                }

                return 0;
            };
            const typoWordScore = (queryWords, haystack) => {
                const haystackWords = haystack
                    .split(/[^a-z0-9]+/)
                    .map(compactSearch)
                    .filter((word) => word.length > 1);

                if (haystackWords.length === 0) {
                    return 0;
                }

                let totalScore = 0;

                for (const queryWord of queryWords.map(compactSearch).filter(Boolean)) {
                    let bestDistance = Infinity;
                    let bestWord = '';

                    for (const haystackWord of haystackWords) {
                        const distance = boundedEditDistance(queryWord, haystackWord, typoThreshold(queryWord));

                        if (distance < bestDistance) {
                            bestDistance = distance;
                            bestWord = haystackWord;
                        }
                    }

                    if (bestDistance > typoThreshold(queryWord)) {
                        return 0;
                    }

                    totalScore += Math.max(0, 640 - (bestDistance * 120) - Math.abs(bestWord.length - queryWord.length) * 12);
                }

                return Math.round(totalScore / Math.max(1, queryWords.length));
            };
            const typoThreshold = (word) => {
                if (word.length <= 3) {
                    return 0;
                }

                return word.length <= 6 ? 1 : 2;
            };
            const boundedEditDistance = (left, right, maxDistance) => {
                if (Math.abs(left.length - right.length) > maxDistance) {
                    return maxDistance + 1;
                }

                const previous = Array.from({ length: right.length + 1 }, (_, index) => index);

                for (let leftIndex = 1; leftIndex <= left.length; leftIndex += 1) {
                    let diagonal = previous[0];
                    previous[0] = leftIndex;
                    let rowMin = previous[0];

                    for (let rightIndex = 1; rightIndex <= right.length; rightIndex += 1) {
                        const temp = previous[rightIndex];
                        previous[rightIndex] = Math.min(
                            previous[rightIndex] + 1,
                            previous[rightIndex - 1] + 1,
                            diagonal + (left[leftIndex - 1] === right[rightIndex - 1] ? 0 : 1),
                        );
                        diagonal = temp;
                        rowMin = Math.min(rowMin, previous[rightIndex]);
                    }

                    if (rowMin > maxDistance) {
                        return maxDistance + 1;
                    }
                }

                return previous[right.length];
            };
            const fuzzySequenceScore = (needle, haystack) => {
                if (needle.length < 2 || haystack.length === 0 || needle.length > haystack.length) {
                    return 0;
                }

                let needleIndex = 0;
                let firstMatch = -1;
                let lastMatch = -1;

                for (let index = 0; index < haystack.length && needleIndex < needle.length; index += 1) {
                    if (haystack[index] === needle[needleIndex]) {
                        if (firstMatch === -1) {
                            firstMatch = index;
                        }

                        lastMatch = index;
                        needleIndex += 1;
                    }
                }

                if (needleIndex !== needle.length) {
                    return 0;
                }

                const spread = Math.max(1, lastMatch - firstMatch + 1);
                const density = needle.length / spread;
                const startBonus = Math.max(0, 80 - firstMatch);
                const score = Math.round(260 + (density * 260) + startBonus);

                return score >= 420 ? score : 0;
            };
            const exactSearchMatch = () => {
                const query = normalizeSearch(searchInput?.value);
                if (query === '') {
                    return null;
                }

                return Object.values(products).find((product) => normalizeSearch(product.barcode) === query)
                    || Object.values(products).find((product) => normalizeSearch(product.sku) === query)
                    || null;
            };
            const isScannerLikeInput = (query) => {
                if (query.length < 4 || searchKeyTimings.length < 2) {
                    return false;
                }

                const gaps = [];
                for (let index = 1; index < searchKeyTimings.length; index += 1) {
                    gaps.push(searchKeyTimings[index] - searchKeyTimings[index - 1]);
                }

                const averageGap = gaps.reduce((sum, gap) => sum + gap, 0) / gaps.length;
                return averageGap > 0 && averageGap < 50;
            };
            const queueScanLookup = (query, scannerMode = false) => {
                const normalizedQuery = String(query || '').replace(/[\r\n]/g, '').trim();
                if (normalizedQuery === '') {
                    return;
                }

                scanQueue.push({ query: normalizedQuery, scannerMode });
                processNextScanLookup();
            };
            const processNextScanLookup = async () => {
                if (scanLookupRunning || scanQueue.length === 0) {
                    return;
                }

                scanLookupRunning = true;
                const scan = scanQueue.shift();

                try {
                    await lookupAndApplyScan(scan.query, scan.scannerMode);
                } finally {
                    scanLookupRunning = false;
                    processNextScanLookup();
                }
            };
            const lookupAndApplyScan = async (query, scannerMode) => {
                if (!navigator.onLine) {
                    showPosNotice('Barcode lookup is unavailable while offline.', 'warning');
                    searchInput?.focus();
                    return;
                }

                const url = new URL(root.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('scanner_mode', scannerMode ? '1' : '0');

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (response.status === 409) {
                    showPosNotice(payload.message || 'This barcode is assigned to multiple variants. Please correct the product data.', 'danger');
                    searchInput?.focus();
                    return;
                }

                if (!response.ok) {
                    showPosNotice(payload.message || 'Barcode lookup failed. Please try again.', 'danger');
                    searchInput?.focus();
                    return;
                }

                if (payload.auto_add && payload.item) {
                    const product = rememberProduct(payload.item);
                    const result = product ? addItem(String(product.id)) : { added: false, message: 'Product is not available in this POS.' };

                    if (result.added) {
                        searchInput.value = '';
                        filterProducts();
                        showPosNotice(`${product.product_name || product.name} added`, 'success');
                    } else {
                        showPosNotice(result.message || 'Product could not be added.', 'warning');
                    }

                    searchInput?.focus();
                    return;
                }

                if (scannerMode || payload.match_type === 'none') {
                    showPosNotice(payload.message || `No product found for barcode: ${query}`, 'warning');
                    searchInput?.select();
                } else {
                    filterProducts();
                }

                searchInput?.focus();
            };
            const heldTotal = (snapshot) => (snapshot.items || []).reduce((sum, row) => sum + (Number(row.price) * Number(row.quantity)), 0);
            const heldItemCount = (snapshot) => (snapshot.items || []).reduce((sum, row) => sum + Number(row.quantity), 0);
            const heldNumber = (snapshot, index) => `HOLD-${String(index + 1).padStart(3, '0')}`;
            const renderHeldCarts = () => {
                heldCountEls.forEach((countEl) => {
                    countEl.textContent = heldCarts.length;
                });
                heldEmptyEl.classList.toggle('d-none', heldCarts.length > 0);
                heldListEl.innerHTML = heldCarts.map((snapshot, index) => `
                    <div class="border rounded p-3 d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">${escapeHtml(snapshot.label || 'Walk-in customer')}</div>
                            <div class="text-muted fs-sm">${heldNumber(snapshot, index)} · ${heldItemCount(snapshot)} item(s) · ${money.format(heldTotal(snapshot))}</div>
                            <div class="text-muted fs-sm">Order time ${formatElapsed(Number(snapshot.elapsedSeconds || 0))}</div>
                        </div>
                        <div class="d-flex gap-2 flex-shrink-0">
                            <button type="button" class="btn btn-light btn-sm js-pos-held-delete" data-held-id="${escapeHtml(snapshot.id)}">
                                <i class="ph-trash me-1"></i>
                                Delete
                            </button>
                            <button type="button" class="btn btn-primary btn-sm js-pos-held-resume" data-held-id="${escapeHtml(snapshot.id)}">
                                <i class="ph-arrow-counter-clockwise me-1"></i>
                                Resume
                            </button>
                        </div>
                    </div>
                `).join('');
            };
            const clearActiveCart = () => {
                cart.clear();
                cashInput.value = '';
                resetTimer();
                clearSavedCart();
                render();
            };
            const holdCart = () => {
                if (cart.size === 0) {
                    showMessage('Nothing to hold', 'Add products before holding an order.');
                    return;
                }

                promptHoldLabel((label) => {
                    pauseTimer();
                    heldCarts.unshift(cartSnapshot({
                        label: label.trim(),
                        heldAt: new Date().toISOString(),
                    }));
                    writeHeldCarts();
                    renderHeldCarts();
                    clearActiveCart();
                });
            };
            const showHeldOrders = () => {
                renderHeldCarts();

                if (window.bootstrap?.Modal && heldModalEl) {
                    window.bootstrap.Modal.getOrCreateInstance(heldModalEl).show();
                    return;
                }

                showMessage('Held orders', heldCarts.length === 0 ? 'No held orders.' : 'Held orders are available in this browser.');
            };
            const resumeHeldCart = (heldId) => {
                const snapshot = heldCarts.find((row) => row.id === heldId);
                if (!snapshot) {
                    return;
                }

                confirmResumeHeldCart(cart.size > 0, () => {
                    heldCarts = heldCarts.filter((row) => row.id !== heldId);
                    writeHeldCarts();
                    loadSnapshot(snapshot);
                    saveCart();
                    renderHeldCarts();
                    render();

                    if (window.bootstrap?.Modal && heldModalEl) {
                        window.bootstrap.Modal.getOrCreateInstance(heldModalEl).hide();
                    }
                });
            };
            const deleteHeldCart = (heldId) => {
                confirmDeleteHeldCart(() => {
                    heldCarts = heldCarts.filter((row) => row.id !== heldId);
                    writeHeldCarts();
                    renderHeldCarts();
                });
            };
            const showRecentSales = async () => {
                recentSalesLoadingEl.classList.remove('d-none');
                recentSalesEmptyEl.classList.add('d-none');
                recentSalesListEl.innerHTML = '';

                if (window.bootstrap?.Modal && recentSalesModalEl) {
                    window.bootstrap.Modal.getOrCreateInstance(recentSalesModalEl).show();
                }

                try {
                    const response = await fetch(root.dataset.recentSalesUrl, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Unable to load recent sales.');
                    }

                    renderRecentSales(payload.sales || []);
                } catch (error) {
                    recentSalesListEl.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            ${escapeHtml(error.message)}
                        </div>
                    `;
                } finally {
                    recentSalesLoadingEl.classList.add('d-none');
                }
            };
            const renderRecentSales = (sales) => {
                recentSalesEmptyEl.classList.toggle('d-none', sales.length > 0);
                recentSalesListEl.innerHTML = sales.map((sale) => `
                    <div class="border rounded p-3 d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">${escapeHtml(sale.number)}</div>
                            <div class="text-muted fs-sm">${escapeHtml(sale.created_at || '-')}</div>
                            <div class="fw-semibold mt-1">INR ${escapeHtml(sale.grand_total)}</div>
                        </div>
                        <div class="d-flex gap-2 flex-shrink-0">
                            <a href="${escapeHtml(sale.receipt_url)}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                                <i class="ph-receipt me-1"></i>
                                View receipt
                            </a>
                            <a href="${escapeHtml(sale.print_url)}" target="_blank" rel="noopener" class="btn btn-light btn-sm">
                                <i class="ph-printer me-1"></i>
                                Print
                            </a>
                        </div>
                    </div>
                `).join('');
            };

            const rememberProduct = (item) => {
                const variantId = String(item.variant_id || item.id || '');
                if (variantId === '') {
                    return null;
                }

                products[variantId] = {
                    ...item,
                    id: variantId,
                    price: Number(item.price ?? item.selling_price ?? 0),
                    stock: Number(item.stock ?? 0),
                };

                return products[variantId];
            };

            const addItem = (variantId) => {
                const product = products[variantId];
                if (!product) {
                    return { added: false, reason: 'missing', message: 'Product is not available in this POS.' };
                }

                if (Number(product.stock) < 1) {
                    return { added: false, reason: 'out_of_stock', message: 'This item is out of stock.' };
                }

                const existing = cart.get(variantId);
                if (existing) {
                    if (existing.quantity >= Number(product.stock)) {
                        return { added: false, reason: 'stock_limit', message: `Only ${product.stock} units available.` };
                    }

                    existing.quantity += 1;
                } else {
                    startTimer();
                    cart.set(variantId, {
                        ...product,
                        id: variantId,
                        quantity: 1,
                    });
                }

                render();
                saveCart();
                return { added: true, product };
            };

            const updateQuantity = (variantId, delta) => {
                const row = cart.get(variantId);
                if (!row) {
                    return;
                }

                row.quantity += delta;
                if (row.quantity < 1) {
                    cart.delete(variantId);
                } else {
                    row.quantity = Math.min(row.quantity, row.stock);
                }

                render();
                saveCart();
            };

            root.addEventListener('click', (event) => {
                const addButton = event.target.closest('.js-pos-add');
                const addCard = event.target.closest('.js-pos-add-card');
                const cartRow = event.target.closest('.pos-cart-row');

                if (addButton) {
                    addItem(addButton.dataset.variantId);
                    return;
                }

                if (addCard && !event.target.closest('button')) {
                    addItem(addCard.dataset.variantId);
                    return;
                }

                if (event.target.closest('.js-pos-clear')) {
                    confirmClearCart(() => {
                        clearActiveCart();
                    });
                    return;
                }

                if (event.target.closest('.js-pos-hold')) {
                    holdCart();
                    return;
                }

                if (event.target.closest('.js-pos-held-orders')) {
                    showHeldOrders();
                    return;
                }

                if (event.target.closest('.js-pos-recent-sales')) {
                    showRecentSales();
                    return;
                }

                if (event.target.closest('.js-pos-reprint-last')) {
                    const receipt = lastReceipt();
                    if (receipt?.printUrl) {
                        window.open(receipt.printUrl, '_blank', 'noopener');
                    }
                    return;
                }

                if (event.target.closest('.js-pos-shortcuts')) {
                    showKeyboardShortcuts();
                    return;
                }

                if (event.target.closest('.js-pos-complete')) {
                    completeSale();
                    return;
                }

                if (!cartRow) {
                    return;
                }

                const variantId = cartRow.dataset.variantId;
                if (event.target.closest('.js-pos-remove')) {
                    cart.delete(variantId);
                    render();
                    saveCart();
                } else if (event.target.closest('.js-pos-decrease')) {
                    updateQuantity(variantId, -1);
                } else if (event.target.closest('.js-pos-increase')) {
                    updateQuantity(variantId, 1);
                }
            });

            heldModalEl?.addEventListener('click', (event) => {
                const resumeButton = event.target.closest('.js-pos-held-resume');
                const deleteButton = event.target.closest('.js-pos-held-delete');

                if (resumeButton) {
                    resumeHeldCart(resumeButton.dataset.heldId);
                    return;
                }

                if (deleteButton) {
                    deleteHeldCart(deleteButton.dataset.heldId);
                }
            });

            root.addEventListener('keydown', (event) => {
                if (!['Enter', ' '].includes(event.key)) {
                    return;
                }

                const addCard = event.target.closest('.js-pos-add-card');
                if (!addCard) {
                    return;
                }

                event.preventDefault();
                addItem(addCard.dataset.variantId);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === '/' && !isTypingTarget(event.target)) {
                    event.preventDefault();
                    searchInput?.focus();
                    return;
                }

                if (event.key === 'F4') {
                    event.preventDefault();
                    holdCart();
                    return;
                }

                if (event.key === 'F9') {
                    event.preventDefault();
                    completeSale();
                    return;
                }

                if (event.altKey && event.key.toLowerCase() === 'h') {
                    event.preventDefault();
                    showHeldOrders();
                    return;
                }

                if (event.altKey && event.key.toLowerCase() === 'r') {
                    event.preventDefault();
                    const receipt = lastReceipt();
                    if (receipt?.printUrl) {
                        window.open(receipt.printUrl, '_blank', 'noopener');
                    }
                }
            });

            searchInput?.addEventListener('keydown', (event) => {
                if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    searchKeyTimings.push(Date.now());
                    searchKeyTimings = searchKeyTimings.slice(-24);
                }

                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (event.repeat) {
                        return;
                    }

                    const query = searchInput.value || '';
                    queueScanLookup(query, isScannerLikeInput(query));
                    searchKeyTimings = [];
                    return;
                }

                if (['Tab', 'ArrowRight'].includes(event.key) && acceptSearchSuggestion()) {
                    event.preventDefault();
                }
            });
            searchInput?.addEventListener('click', updateSearchSuggestion);
            searchInput?.addEventListener('keyup', updateSearchSuggestion);
            searchInput?.addEventListener('input', filterProducts);
            searchClearButton?.addEventListener('click', () => {
                searchInput.value = '';
                filterProducts();
                searchInput.focus();
            });
            searchForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                queueScanLookup(searchInput.value || '', false);
            });
            cashInput.addEventListener('input', () => {
                render();
                saveCart();
            });
            paymentMethodInput.addEventListener('change', renderPaymentMethod);
            root.querySelectorAll('input[name="fulfilment_type"]').forEach((input) => {
                input.addEventListener('change', saveCart);
            });
            loadHeldCarts();
            restoreCart();
            renderLastReceiptButton();
            filterProducts();
            render();

            async function completeSale() {
                const total = cartTotal();
                const method = selectedPaymentMethod();
                const cash = method === 'cash' ? Number.parseFloat(cashInput.value || '0') : total;

                if (cart.size === 0 || (method === 'cash' && cash < total)) {
                    return;
                }

                completeButton.disabled = true;
                completeButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Completing';

                try {
                    const response = await fetch(root.dataset.checkoutUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            amount_paid: cash,
                            elapsed_seconds: elapsedSeconds(),
                            fulfilment_type: selectedFulfilment(),
                            payment_method: method,
                            items: Array.from(cart.values()).map((row) => ({
                                product_variant_id: Number(row.id),
                                quantity: row.quantity,
                            })),
                        }),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(firstError(payload) || 'Unable to complete this sale.');
                    }

                    saveLastReceipt(payload.order);
                    clearActiveCart();
                    renderLastReceiptButton();
                    showReceipt(payload.order);
                } catch (error) {
                    showMessage('Checkout failed', error.message);
                    render();
                } finally {
                    completeButton.innerHTML = '<i class="ph-check-circle me-2"></i>Complete Sale';
                }
            }
        });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function paymentMethodLabel(method) {
            return {
                cash: 'Cash',
                upi: 'UPI',
                card: 'Card',
                wallet: 'Wallet',
                other: 'Other',
            }[method] || 'Cash';
        }

        function confirmClearCart(onConfirm) {
            if (typeof bootbox === 'undefined') {
                if (window.confirm('Clear the current order?')) {
                    onConfirm();
                }

                return;
            }

            bootbox.confirm({
                title: 'Clear the current order?',
                message: 'Every item, the assigned customer, and any discount will be removed. This cannot be undone.',
                centerVertical: true,
                buttons: {
                    cancel: {
                        label: 'Keep',
                        className: 'btn-light',
                    },
                    confirm: {
                        label: 'Clear order',
                        className: 'btn-danger',
                    },
                },
                callback: (confirmed) => {
                    if (confirmed) {
                        onConfirm();
                    }
                },
            });
        }

        function promptHoldLabel(onConfirm) {
            if (typeof bootbox === 'undefined') {
                onConfirm(window.prompt('Hold label or customer name', '') || '');
                return;
            }

            bootbox.prompt({
                title: 'Hold this order',
                message: 'Optional label - table number, customer name, anything you will recognise.',
                inputType: 'text',
                placeholder: 'e.g. Table 5 - Mrs. Patel - Pickup order',
                centerVertical: true,
                buttons: {
                    cancel: {
                        label: 'Cancel',
                        className: 'btn-light',
                    },
                    confirm: {
                        label: 'Hold order',
                        className: 'btn-primary',
                    },
                },
                callback: (label) => {
                    if (label !== null) {
                        onConfirm(String(label));
                    }
                },
            });
        }

        function confirmResumeHeldCart(hasActiveCart, onConfirm) {
            if (!hasActiveCart) {
                onConfirm();
                return;
            }

            if (typeof bootbox === 'undefined') {
                if (window.confirm('Replace the current cart with this held order?')) {
                    onConfirm();
                }

                return;
            }

            bootbox.confirm({
                title: 'Resume held order?',
                message: 'The current cart has items. Resuming this held order will replace the current cart.',
                centerVertical: true,
                buttons: {
                    cancel: {
                        label: 'Keep current cart',
                        className: 'btn-light',
                    },
                    confirm: {
                        label: 'Resume held order',
                        className: 'btn-primary',
                    },
                },
                callback: (confirmed) => {
                    if (confirmed) {
                        onConfirm();
                    }
                },
            });
        }

        function confirmDeleteHeldCart(onConfirm) {
            if (typeof bootbox === 'undefined') {
                if (window.confirm('Delete this held order?')) {
                    onConfirm();
                }

                return;
            }

            bootbox.confirm({
                title: 'Delete held order?',
                message: 'This paused cart will be discarded. Completed sales are not affected.',
                centerVertical: true,
                buttons: {
                    cancel: {
                        label: 'Keep',
                        className: 'btn-light',
                    },
                    confirm: {
                        label: 'Delete',
                        className: 'btn-danger',
                    },
                },
                callback: (confirmed) => {
                    if (confirmed) {
                        onConfirm();
                    }
                },
            });
        }

        function showKeyboardShortcuts() {
            const message = `
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span>Focus search</span>
                        <kbd>/</kbd>
                    </div>
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span>Hold order</span>
                        <kbd>F4</kbd>
                    </div>
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span>Checkout</span>
                        <kbd>F9</kbd>
                    </div>
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span>Held orders</span>
                        <kbd>Alt+H</kbd>
                    </div>
                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span>Reprint last receipt</span>
                        <kbd>Alt+R</kbd>
                    </div>
                </div>
            `;

            if (typeof bootbox === 'undefined') {
                window.alert('Keyboard shortcuts\n\n/ Focus search\nF4 Hold order\nF9 Checkout\nAlt+H Held orders\nAlt+R Reprint last receipt');
                return;
            }

            bootbox.alert({
                title: 'Keyboard shortcuts',
                message,
                centerVertical: true,
            });
        }

        function firstError(payload) {
            const errors = payload?.errors || {};
            const first = Object.values(errors)[0];

            return Array.isArray(first) ? first[0] : payload?.message;
        }

        function showMessage(title, message) {
            if (typeof bootbox === 'undefined') {
                window.alert(`${title}\n\n${message}`);
                return;
            }

            bootbox.alert({
                title,
                message,
                centerVertical: true,
            });
        }

        function showPosNotice(message, type = 'info') {
            const existing = document.querySelector('.js-pos-scan-notice');
            if (existing) {
                existing.remove();
            }

            const alertType = ['success', 'warning', 'danger', 'info'].includes(type) ? type : 'info';
            const notice = document.createElement('div');
            notice.className = `alert alert-${alertType} pos-scan-notice js-pos-scan-notice mb-0`;
            notice.setAttribute('role', 'status');
            notice.textContent = message;
            document.body.appendChild(notice);

            window.setTimeout(() => {
                notice.remove();
            }, alertType === 'success' ? 1300 : 2600);
        }

        function showReceipt(order) {
            const message = `
                <div class="text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 72px; height: 72px;">
                        <i class="ph-check ph-2x"></i>
                    </div>
                    <div class="fw-bold fs-5 mb-1">Sale complete</div>
                    <div class="text-muted mb-3">${escapeHtml(order.number)}</div>
                    <div class="display-6 fw-bold mb-2">INR ${escapeHtml(order.grand_total)}</div>
                    <div class="text-muted">
                        Change INR ${escapeHtml(order.change_amount)}
                    </div>
                    <div class="text-muted mt-1">
                        ${escapeHtml(paymentMethodLabel(order.payment_method))}
                    </div>
                </div>
            `;

            if (typeof bootbox === 'undefined') {
                window.alert(`Sale completed\nOrder ${order.number}`);
                window.location.reload();
                return;
            }

            bootbox.dialog({
                message,
                centerVertical: true,
                closeButton: false,
                buttons: {
                    view: {
                        label: '<i class="ph-receipt me-1"></i>View receipt',
                        className: 'btn-light',
                        callback: () => {
                            window.open(order.receipt_url, '_blank', 'noopener');
                            return false;
                        },
                    },
                    print: {
                        label: '<i class="ph-printer me-1"></i>Print receipt',
                        className: 'btn-light',
                        callback: () => {
                            window.open(order.print_url, '_blank', 'noopener');
                            return false;
                        },
                    },
                    newSale: {
                        label: 'New sale',
                        className: 'btn-primary',
                        callback: () => {
                            window.location.reload();
                        },
                    },
                },
            });
        }
    </script>
@endpush
