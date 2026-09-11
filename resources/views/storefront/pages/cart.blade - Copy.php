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

        /* ---------- Reference-aligned cart layout (behavior hooks untouched) ---------- */
        .ws-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 1px 2px rgb(16 24 40 / 4%);
        }

        .lp-cart-count {
            color: var(--text-3);
            font-size: 15px;
            font-weight: 500;
        }

        .lp-cart-sub {
            margin: 8px auto 0;
            max-width: 560px;
            color: var(--text-2);
            font-size: 14px;
            line-height: 22px;
        }

        .lp-saved-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            padding: 9px 18px;
            border: 1px solid #12b98130;
            border-radius: 999px;
            background: #12b98110;
            color: #166534;
            font-size: 13px;
            line-height: 18px;
        }

        .lp-saved-pill .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #10b981;
            flex: 0 0 auto;
        }

        .lp-saved-pill a {
            font-weight: 700;
        }

        .cart-breadcrumb-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 22px 0 14px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 18px;
        }

        .cart-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-3);
            font-size: 13px;
            line-height: 20px;
        }

        .cart-breadcrumbs .current {
            color: var(--text-1);
            font-weight: 600;
        }

        .cart-page-heading {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }

        .cart-page-heading h1 {
            margin: 0;
            font-size: 22px;
            line-height: 30px;
            font-weight: 700;
        }

        .cart-shop-list {
            display: grid;
            gap: 24px;
        }

        .cart-shop-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: #fff;
        }

        @media (min-width: 576px) {
            .cart-shop-header {
                padding: 14px 24px;
            }
        }

        .cart-shop-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .cart-shop-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            flex: 0 0 auto;
        }

        .cart-shop-heading h6 {
            margin: 0;
            font-size: 15px;
            line-height: 20px;
        }

        .cart-shop-meta {
            color: var(--text-3);
            font-size: 12px;
            line-height: 18px;
        }

        .cart-shop-chip {
            display: none;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            font-size: 11px;
            line-height: 18px;
            font-weight: 600;
            white-space: nowrap;
        }

        @media (min-width: 576px) {
            .cart-shop-chip {
                display: inline-flex;
            }
        }

        .cart-shop-items {
            display: grid;
        }

        /* Product row: image | body */
        .cart-shop-item {
            display: flex;
            gap: 12px;
            padding: 14px 12px;
            border-bottom: 1px solid var(--line);
        }

        @media (min-width: 576px) {
            .cart-shop-item {
                gap: 16px;
                padding: 20px 24px;
            }
        }

        .cart-shop-item:last-child {
            border-bottom: 0;
        }

        .ws-img {
            width: 84px;
            height: 104px;
            object-fit: cover;
            border-radius: 8px;
            background: #f4f4f4;
            flex-shrink: 0;
        }

        .cart-shop-item.is-unavailable .ws-img {
            opacity: .55;
        }

        @media (min-width: 576px) {
        .ws-img {
                width: 96px;
                height: 118px;
            }
        }

        .cart-product-main {
            display: contents;
        }

        .lp-row-body {
            flex-grow: 1;
            min-width: 0;
        }

        .lp-row-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }

        .lp-row-title {
            display: flex;
            align-items: flex-start;
            flex-wrap: wrap;
            column-gap: 10px;
            row-gap: 4px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .lp-title-offer {
            display: contents;
        }

        @media (max-width: 575px) {
            .lp-title-offer {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                width: 100%;
                min-width: 0;
            }
        }

        .lp-item-variant {
            flex-basis: 100%;
            color: var(--text-3);
            font-size: 12px;
            line-height: 18px;
        }

        @media (min-width: 576px) {
            .lp-row-title .cart-offer-panel {
                transform: translateY(-2px);
            }
        }

        .lp-item-name {
            display: inline-block;
            width: auto;
            max-width: 100%;
            font-size: 14px;
            line-height: 22px;
            font-weight: 500;
            min-width: 0;
        }

        @media (max-width: 575px) {
            .lp-item-name {
                display: block;
            }
        }

        .lp-remove-btn {
            align-items: center;
            gap: 6px;
            padding: 0;
            border: 0;
            background: none;
            color: #64748b;
            font-size: 13px;
            line-height: 18px;
            font-weight: 500;
            white-space: nowrap;
            cursor: pointer;
            flex-shrink: 0;
        }

        .lp-remove-btn:hover {
            color: #334155;
        }

        .lp-remove-btn .icon {
            margin-right: 0 !important;
            color: inherit;
            font-size: 14px;
            line-height: 1;
        }

        /* Offer boxes with icon tile */
        .cart-offer-panel {
            display: inline-flex;
            align-items: flex-start;
            gap: 8px;
            padding: 7px 9px;
            border-radius: 8px;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            max-width: 100%;
            flex: 0 1 auto;
            margin: 0;
        }

        .cart-offer-panel.is-coupon {
            background: #FFF7ED;
            border-color: #FDBA7466;
        }

        .cart-offer-panel.is-blue {
            background: #EFF6FF;
            border-color: #BFDBFE;
        }

        .cart-offer-panel[hidden] {
            display: none;
        }

        .lp-offer-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 5px;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .lp-offer-icon .fas {
            font-size: 10px;
            line-height: 1;
        }

        .lp-offer-icon.is-coupon {
            background: #F97316;
        }

        .lp-offer-icon.is-auto {
            background: #059669;
        }

        .lp-offer-icon.is-blue {
            background: #2563EB;
        }

        .lp-offer-text {
            display: block;
            line-height: 1.2;
            min-width: 0;
        }

        .cart-offer-label {
            font-size: 12px;
            font-weight: 600;
            color: #065F46;
            white-space: normal;
        }

        .cart-offer-panel.is-coupon .cart-offer-label {
            color: #9A3412;
        }

        .cart-offer-panel.is-blue .cart-offer-label {
            color: #1E40AF;
        }

        .cart-offer-meta,
        .cart-offer-savings,
        .cart-line-note {
            font-size: 11px;
            line-height: 16px;
        }

        .cart-offer-inline {
            display: block;
            margin-top: 1px;
        }

        .cart-offer-panel.is-coupon .cart-offer-meta,
        .cart-offer-panel.is-coupon .cart-offer-savings {
            color: #C2410C;
        }

        .cart-offer-panel:not(.is-coupon):not(.is-blue) .cart-offer-meta,
        .cart-offer-panel:not(.is-coupon):not(.is-blue) .cart-offer-savings {
            color: #047857;
        }

        .cart-offer-panel.is-blue .cart-offer-meta,
        .cart-offer-panel.is-blue .cart-offer-savings {
            color: #2563EB;
        }

        .lp-row-bottom {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 8px;
            margin-top: 12px;
        }

        .lp-row-qty {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lp-row-policy {
            color: var(--text-3);
            font-size: 12px;
        }

        .lp-row-money {
            text-align: right;
            margin-left: auto;
        }

        .lp-unit-note {
            color: var(--text-3);
            font-size: 12px;
        }

        .lp-money-line {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }

        .cart-base-subtotal {
            color: var(--text-3);
            font-size: 12px;
            text-decoration: line-through;
        }

        .lp-final-price {
            font-size: 15px;
            font-weight: 700;
            color: #111;
        }

        .lp-save-line {
            color: #198754;
            font-size: 11px;
            font-weight: 500;
        }

        .lp-policy-mobile {
            color: var(--text-3);
            font-size: 11px;
        }

        .cart-item-controls {
            display: contents;
        }

        .cart-product-actions {
            display: contents;
        }

        .cart-product-money {
            display: contents;
        }

        /* Free gift */
        .cart-shop-item.is-generated-gift {
            align-items: center;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 10px;
            margin: 12px;
            padding: 12px;
        }

        @media (min-width: 576px) {
            .cart-shop-item.is-generated-gift {
                margin: 0;
                border: 0;
                border-bottom: 1px solid var(--line);
                border-radius: 0;
                padding: 14px 24px;
            }
        }

        .ws-gift-img {
            position: relative;
            flex-shrink: 0;
            padding: 0;
        }

        .ws-gift-img .ws-img {
            border: 1px solid #FDE68A;
        }

        .ws-gift-tag {
            position: absolute;
            top: 6px;
            left: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #111827;
            border: 1px solid #fff;
            color: #fff;
            font-size: 9px;
            letter-spacing: .12em;
            font-weight: 700;
            box-shadow: 0 1px 2px rgb(0 0 0 / 20%);
        }

        .lp-gift-kicker {
            font-size: 10px;
            letter-spacing: .12em;
            font-weight: 700;
            color: #92400E;
        }

        .lp-gift-value {
            color: var(--text-3);
            font-size: 12px;
        }

        .lp-gift-by {
            margin-top: 4px;
            color: var(--text-3);
            font-size: 11px;
        }

        .cart-gift-lock {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            color: var(--text-3);
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Coupon box */
        .cart-shop-footer {
            padding: 0;
            background: #f8fafc;
            border-top: 1px solid var(--line);
        }

        @media (min-width: 576px) {
            .cart-shop-footer {
                padding: 0;
            }
        }

        .ws-coupon-box {
            padding: 16px 12px;
        }

        @media (min-width: 576px) {
            .ws-coupon-box {
                padding: 18px 24px;
            }
        }

        .lp-coupon-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: var(--text-3);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .coupon-unapplied[hidden],
        .coupon-applied[hidden] {
            display: none !important;
        }

        .cart-shop-coupon-form {
            display: flex;
            gap: 8px;
        }

        .cart-shop-coupon-form .form-control {
            flex: 1 1 auto;
            min-width: 0;
            height: 40px;
            border-radius: 999px;
            font-size: 14px;
        }

        .coupon-input-wrap {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
        }

        .coupon-input-wrap .form-control {
            padding-right: 42px;
            background: #fff;
        }

        .coupon-input-wrap .coupon-input-icon {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: 12px;
            opacity: .55;
            pointer-events: none;
        }

        .lp-btn-dark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #111827;
            border: 1px solid #111827;
            border-radius: 999px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .lp-btn-dark:hover {
            color: #fff;
            background: #1F2937;
        }

        button.lp-btn-dark {
            height: 40px;
            padding: 0 24px;
        }

        a.lp-btn-dark {
            min-height: 42px;
            padding: 10px 24px;
            text-decoration: none;
        }

        .lp-coupon-applied {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 1px solid #A7F3D0;
            border-radius: 999px;
            background: #fff;
            padding: 8px 8px 8px 14px;
            font-size: 12px;
        }

        .coupon-applied .text-success {
            color: #15803d !important;
        }

        .cart-coupon-code {
            color: #065F46;
            font-weight: 700;
            text-transform: uppercase;
        }

        .lp-coupon-remove {
            padding: 4px 8px;
            border: 0;
            background: none;
            color: var(--text-3);
            font-size: 12px;
            text-decoration: underline;
            cursor: pointer;
        }

        .cart-coupon-state {
            font-size: 12px;
            min-height: 18px;
        }

        .lp-shop-totals {
            margin-top: 12px;
            font-size: 13px;
        }

        .lp-shop-totals .cart-shop-total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 2px 0;
        }

        .lp-shop-totals .is-savings {
            color: #15803d;
            font-weight: 600;
            font-size: 12px;
        }

        .cart-shop-total-row.is-total {
            font-weight: 800;
        }

        .lp-shop-checkout {
            margin-top: 12px;
            display: grid;
            gap: 8px;
        }

        .lp-shop-checkout .lp-btn-dark {
            width: 100%;
        }

        .cart-shop-checkout-note {
            margin: 0;
            color: var(--text-3);
            font-size: 11px;
            line-height: 1.45;
            text-align: center;
        }

        .lp-secure-strip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 0 2px;
            color: var(--text-3);
            font-size: 12px;
        }

        /* Summary */
        .cart-summary-card {
            padding: 24px;
        }

        .cart-summary-title {
            color: #111;
            font-size: 15px;
            line-height: 22px;
            font-weight: 700;
        }

        .cart-summary-subtitle {
            color: #7c8493;
            font-size: 13px;
            line-height: 20px;
        }

        .lp-summary-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            align-self: flex-start;
            padding: 5px 14px;
            border-radius: 999px;
            background: #dffbea;
            border: 1px solid #8df0bc;
            color: #047857;
            font-size: 12px;
            line-height: 18px;
            font-weight: 700;
        }

        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
            color: #6b7280;
            font-size: 14px;
            line-height: 22px;
        }

        .cart-summary-row span:last-child {
            text-align: right;
        }

        .cart-summary-row.is-savings {
            color: #047857;
            font-size: 13px;
            font-weight: 600;
        }

        .cart-summary-row.is-savings span:first-child {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .cart-summary-row.is-total {
            margin-top: 6px;
            margin-bottom: 0;
            color: #111;
            font-size: 15px;
            font-weight: 700;
        }

        .cart-summary-row.is-total [data-cart-total] {
            color: #111;
            font-size: 22px;
            line-height: 28px;
            font-weight: 800;
        }

        .cart-summary-divider {
            margin: 18px 0;
            border: 0;
            border-top: 1px solid #d1d5db;
            opacity: 1;
        }

        .cart-summary-actions {
            margin-top: 26px;
            display: grid;
            gap: 12px;
        }

        .cart-summary-action-note,
        .cart-summary-secondary-note {
            margin: 0;
            color: #697386;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
        }

        .cart-summary-secondary-note {
            color: #9aa3b2;
        }

        .cart-summary-cta {
            min-height: 48px;
            border-radius: 999px;
            background: #111;
            border-color: #111;
            font-size: 15px;
            font-weight: 700;
        }

        .cart-summary-cta:hover {
            background: #000;
            border-color: #000;
        }

        .cart-summary-note {
            margin-top: -2px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f3fbf6;
            border: 1px solid #19875424;
            color: #146c43;
        }

        .cart-summary-helper {
            padding: 14px 0 8px;
            border-top: 1px solid var(--line);
            color: var(--text-3);
        }

        .cart-summary-trust {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
            color: var(--text-3);
        }

        .lp-help-card {
            border-style: dashed;
            text-align: center;
            padding: 14px;
        }

        @media (max-width: 991px) {
            .fl-sidebar-cart {
                margin-top: 24px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $offerMeta = static function (?array $promotion): ?string {
            if (empty($promotion)) {
                return null;
            }

            if (($promotion['activation_type'] ?? null) === 'coupon' && filled($promotion['coupon_code'] ?? null)) {
                return 'Coupon: '.$promotion['coupon_code'];
            }

            return 'Applied automatically';
        };

        $offerIconClass = static function (?array $promotion): string {
            if (($promotion['activation_type'] ?? null) === 'coupon') {
                return 'is-coupon';
            }

            return ($promotion['reward_type'] ?? null) === 'fixed_discount' ? 'is-blue' : 'is-auto';
        };

        $offerIconGlyph = static function (?array $promotion): string {
            if (($promotion['activation_type'] ?? null) === 'coupon') {
                return '%';
            }

            return ($promotion['reward_type'] ?? null) === 'fixed_discount' ? '₹' : '✦';
        };

        $shopGroups = collect($cart['shop_groups'] ?? []);
        $cartLineCount = $shopGroups
            ->flatMap(fn (array $group): array => $group['items'] ?? [])
            ->reject(fn (array $item): bool => ! empty($item['is_generated_gift']))
            ->count();
        $shopCount = $shopGroups->count();
        $isSingleShopCart = $shopCount === 1;
        $initials = static function (string $name): string {
            $words = preg_split('/\s+/', trim($name)) ?: [];
            $letters = collect($words)
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => mb_substr($word, 0, 1))
                ->implode('');

            return mb_strtoupper($letters !== '' ? $letters : 'WS');
        };
        $avatarStyle = static function (string $name): string {
            $palette = [
                ['#F3EBFF', '#E8D9FF', '#7C3AED'],
                ['#FFF1F2', '#FFE4E6', '#E11D48'],
                ['#ECFDF5', '#A7F3D0', '#065F46'],
                ['#EFF6FF', '#BFDBFE', '#1E40AF'],
                ['#FFFBEB', '#FDE68A', '#92400E'],
            ];
            $pick = $palette[abs(crc32($name)) % count($palette)];

            return 'background:'.$pick[0].';border:1px solid '.$pick[1].';color:'.$pick[2].';';
        };
    @endphp

    <section class="section-shoping-cart each-list-prd  pb-0" data-cart-page>
        <div class="container">
            <div class="cart-breadcrumb-bar">
                <div>
                    <div class="cart-page-heading mt-2">
                        <h1>Shopping Cart</h1>
                        @if (! $cart['is_empty'])
                            <span class="lp-cart-count">{{ $cartLineCount }} {{ Str::plural('item', $cartLineCount) }} &middot; {{ $shopCount }} {{ Str::plural('shop', $shopCount) }}</span>
                        @endif
                    </div>
                </div>
                
            </div>
        </div>

        <div class="pt-0 pb-3">
            <div class="container">
                <div class="text-center">
                    <div class="lp-saved-pill">
                        <span class="status-dot"></span>
                        <span>Cart saved &middot; Items reserved while available</span>
                        <span>|</span>
                        <a href="{{ route('storefront.products') }}" class="link">Continue shopping</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            @if (session('error'))
                <div class="alert alert-warning mb-24" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div data-cart-empty {{ $cart['is_empty'] ? '' : 'hidden' }}>
                <div class="text-center py-5">
                    <h4 class="mb-12">Your cart is empty</h4>
                    <p class="cl-text-2 mb-24">Add products from local shops to review them here.</p>
                    <a href="{{ route('storefront.products') }}" class="tf-btn animate-btn">
                        <span class="fw-semibold">Continue Shopping</span>
                    </a>
                </div>
            </div>

            <div class="row g-4 align-items-start" data-cart-filled {{ $cart['is_empty'] ? 'hidden' : '' }}>
                <div class="col-lg-8">
                     
                        <div class="d-flex flex-column gap-4" data-cart-items>
                            @foreach ($shopGroups as $shopGroup)
                                @php
                                    $shopItems = collect($shopGroup['items'] ?? []);
                                    $paidItems = $shopItems->reject(fn (array $item): bool => ! empty($item['is_generated_gift']));
                                    $shopOfferCount = collect($shopGroup['applied_promotions'] ?? [])->count();
                                @endphp
                                <section class="cart-shop-card ws-card mb-10" data-cart-shop-card="{{ $shopGroup['shop_id'] }}">
                                    <div class="cart-shop-header">
                                        <div class="cart-shop-heading">
                                            <span class="cart-shop-avatar" style="{{ $avatarStyle($shopGroup['shop_name']) }}">{{ $initials($shopGroup['shop_name']) }}</span>
                                            <div>
                                                <h6>{{ $shopGroup['shop_name'] }}</h6>
                                                <div class="cart-shop-meta">
                                                    <span>{{ $paidItems->count() }} {{ Str::plural('item', $paidItems->count()) }} &middot; {{ $shopOfferCount > 0 ? $shopOfferCount.' '.Str::plural('offer', $shopOfferCount).' applied' : 'Try a coupon' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        @if ($shopOfferCount > 0)
                                            <span class="cart-shop-chip">
                                                <i class="icon icon-Tag"></i>
                                                {{ $shopOfferCount }} {{ Str::plural('offer', $shopOfferCount) }} applied
                                            </span>
                                        @endif
                                    </div>

                                    <div class="cart-shop-items">
                                        @foreach ($shopGroup['items'] as $item)
                                        @php
                                            $promotion = is_array($item['promotion'] ?? null) ? $item['promotion'] : null;
                                            $offerSource = $offerMeta($promotion);
                                            $hasOffer = ! empty($promotion) && (($item['promotion_discount_cents'] ?? 0) > 0 || ! empty($item['is_generated_gift']));
                                            $isGift = ! empty($item['is_generated_gift']);
                                                $isCouponBacked = ($promotion['activation_type'] ?? null) === 'coupon' && filled($promotion['coupon_code'] ?? null);
                                            @endphp
                                            @if ($isGift)
                                                <div class="cart-shop-item is-generated-gift"
                                                    data-cart-item="{{ $item['id'] }}"
                                                    data-cart-shop="{{ $shopGroup['shop_id'] }}">
                                                    <div class="ws-gift-img">
                                                        <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" class="ws-img" loading="lazy">
                                                        <span class="ws-gift-tag">FREE GIFT</span>
                                                    </div>
                                                    <div class="lp-row-body">
                                                        <div class="lp-gift-kicker">FREE GIFT</div>
                                                        <a href="{{ $item['product_url'] }}" class="lp-item-name prd_name link">{{ $item['product_name'] }}</a>
                                                        <div class="lp-gift-value">
                                                            Regular value <span class="text-decoration-line-through" data-cart-item-price>{{ $item['unit_price'] }}</span>
                                                            &middot; <strong class="text-success">FREE / <span data-cart-item-subtotal>{{ $item['line_subtotal'] }}</span></strong>
                                                        </div>
                                                        <div class="lp-gift-by">
                                                            Added by: <span class="fw-medium">{{ $promotion['name'] ?? 'offer' }}</span>@if ($isCouponBacked)
                                                                &middot; Coupon: <strong>{{ $promotion['coupon_code'] }}</strong>
                                                            @endif
                                                        </div>
                                                        <p class="text-caption-01 mt-2 mb-0" data-cart-item-message role="status" hidden></p>
                                                    </div>
                                                    <span class="cart-gift-lock d-none d-sm-inline-flex">
                                                        <i class="icon icon-Lock"></i>
                                                        Gift &mdash; no changes
                                                    </span>
                                                </div>
                                            @else
                                                <div class="cart-shop-item {{ $item['is_available'] ? '' : 'is-unavailable' }}"
                                                    data-cart-item="{{ $item['id'] }}"
                                                    data-cart-shop="{{ $shopGroup['shop_id'] }}">
                                                    <a href="{{ $item['product_url'] }}" class="img-prd d-block flex-shrink-0">
                                                        <img loading="lazy" width="100" height="133"
                                                            src="{{ $item['image'] }}"
                                                            alt="{{ $item['product_name'] }}"
                                                            class="ws-img">
                                                    </a>
                                                    <div class="lp-row-body">
                                                        <div class="lp-row-top">
                                                            <div class="lp-row-title">
                                                                <div class="lp-title-offer">
                                                                    <a href="{{ $item['product_url'] }}"
                                                                        class="lp-item-name prd_name link">
                                                                        {{ $item['product_name'] }}
                                                                    </a>
                                                                    <div class="cart-offer-panel {{ $isCouponBacked ? 'is-coupon' : '' }} {{ $offerIconClass($promotion) === 'is-blue' ? 'is-blue' : '' }}"
                                                                        data-cart-item-offer
                                                                        @if (! $hasOffer) hidden @endif>
                                                                        <span class="lp-offer-icon {{ $offerIconClass($promotion) }}" data-cart-item-offer-icon>
                                                                            @if ($offerIconClass($promotion) === 'is-auto')
                                                                                <i class="fas fa-gift" aria-hidden="true"></i>
                                                                            @else
                                                                                {{ $offerIconGlyph($promotion) }}
                                                                            @endif
                                                                        </span>
                                                                        <span class="lp-offer-text">
                                                                            <span class="cart-offer-label d-block" data-cart-item-offer-name>
                                                                                @if ($promotion)
                                                                                    Offer: {{ $promotion['name'] }}
                                                                                @endif
                                                                            </span>
                                                                            <span class="cart-offer-inline">
                                                                                <span class="cart-offer-meta" data-cart-item-offer-source>{{ $offerSource }}</span>
                                                                                <span class="cart-offer-meta">&middot;</span>
                                                                                <span class="cart-offer-savings" data-cart-item-offer-savings>
                                                                                    @if (($item['promotion_discount_cents'] ?? 0) > 0)
                                                                                        You save {{ ltrim($item['promotion_discount'], '-') }}
                                                                                    @endif
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                @foreach ($item['attributes'] as $attribute)
                                                                    <div class="lp-item-variant prd_select">
                                                                        <span>{{ $attribute['label'] }}: {{ $attribute['value'] }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            <button type="button"
                                                                class="lp-remove-btn cart_remove remove d-none d-sm-inline-flex"
                                                                data-cart-remove-url="{{ $item['remove_url'] }}">
                                                                <i class="icon icon-trash me-1"></i>Remove
                                                            </button>
                                                        </div>
                                                        <div class="lp-row-bottom">
                                                            <div class="lp-row-qty">
                                                                <div class="cart-item-controls wg-quantity" data-cart-title="Quantity">
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
                                                                @if (! empty($item['return_exchange_policy']))
                                                                    <span class="lp-row-policy cart-product-policy d-none d-sm-inline">
                                                                        {{ $item['return_exchange_policy']['inline'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <div class="lp-row-money">
                                                                <div class="lp-unit-note" data-cart-title="Price">
                                                                    <span data-cart-item-price>{{ $item['unit_price'] }}</span> each
                                                                </div>
                                                                <div class="cart-total-stack" data-cart-title="Total Price">
                                                                    <div class="lp-money-line">
                                                                        <span class="cart-base-subtotal"
                                                                            data-cart-item-base-subtotal
                                                                            @if (($item['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                                                            {{ $item['base_line_subtotal'] ?? '' }}
                                                                        </span>
                                                                        <span class="lp-final-price cart_total" data-cart-item-subtotal>{{ $item['line_subtotal'] }}</span>
                                                                    </div>
                                                                    <div class="lp-save-line"
                                                                        data-cart-item-promotion-discount
                                                                        @if (($item['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                                                        {{ ($item['promotion_discount_cents'] ?? 0) > 0 ? 'You save '.ltrim($item['promotion_discount'], '-') : '' }}
                                                                    </div>
                                                                    @if (! empty($item['return_exchange_policy']))
                                                                        <div class="lp-policy-mobile cart-product-policy d-sm-none">
                                                                            {{ $item['return_exchange_policy']['inline'] }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button type="button"
                                                            class="lp-remove-btn cart_remove remove d-sm-none mt-2"
                                                            data-cart-remove-url="{{ $item['remove_url'] }}">
                                                            <i class="icon icon-trash me-1"></i>Remove
                                                        </button>
                                                        @if (! empty($item['availability_message']))
                                                            <p class="text-caption-01 {{ $item['is_available'] ? 'cl-text-2' : 'text-danger' }} mb-0" data-cart-item-warning>
                                                                {{ $item['availability_message'] ?: 'Currently unavailable.' }}
                                                            </p>
                                                        @endif
                                                        <p class="text-caption-01 mt-2 mb-0" data-cart-item-message role="status" hidden></p>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    <div class="cart-shop-footer" data-cart-shop-coupon-row="{{ $shopGroup['shop_id'] }}">
                                        <div class="ws-coupon-box" data-shop-coupon="{{ $shopGroup['shop_id'] }}">
                                            <div class="lp-coupon-label">
                                                <i class="icon icon-Tag"></i>
                                                <span>Coupon for this shop</span>
                                            </div>
                                            <form method="POST"
                                                action="{{ route('storefront.cart.shops.coupon.store', ['shop' => $shopGroup['shop_id']]) }}"
                                                class="coupon-unapplied cart-shop-coupon-form mb-0"
                                                data-coupon-apply-form
                                                {{ ! empty($shopGroup['coupon']['code']) ? 'hidden' : '' }}>
                                                @csrf
                                                <div class="coupon-input-wrap">
                                                    <input
                                                        name="coupon_code"
                                                        class="coupon-input form-control"
                                                        value="{{ $shopGroup['coupon']['code'] ?? '' }}"
                                                        placeholder="Enter coupon code"
                                                        data-coupon-input>
                                                    <span class="coupon-input-icon">⌨</span>
                                                </div>
                                                <button type="submit" class="lp-btn-dark">
                                                    Apply
                                                </button>
                                            </form>
                                            <div class="coupon-applied lp-coupon-applied"
                                                data-coupon-applied-code
                                                {{ empty($shopGroup['coupon']['code']) ? 'hidden' : '' }}>
                                                <span class="small">
                                                    <span class="text-secondary">Coupon</span>
                                                    <span class="cart-coupon-code" data-coupon-code-display>{{ $shopGroup['coupon']['code'] ?? '' }}</span>
                                                    <span class="text-success fw-medium">applied</span>
                                                    <i class="icon icon-CheckCircle1 text-success ms-1"></i>
                                                </span>
                                                <button type="button"
                                                    class="lp-coupon-remove"
                                                    data-coupon-remove-url="{{ route('storefront.cart.shops.coupon.destroy', ['shop' => $shopGroup['shop_id']]) }}"
                                                    {{ empty($shopGroup['coupon']['code']) ? 'hidden' : '' }}>
                                                    Remove
                                                </button>
                                            </div>
                                            <div class="coupon-feedback cart-coupon-state mt-2">
                                                <span class="text-caption-01 {{ (($shopGroup['coupon']['status'] ?? '') === 'applied' || ($shopGroup['coupon']['status'] ?? '') === 'guest_verification_required') ? 'text-success' : 'cl-text-2' }}"
                                                    data-coupon-message>
                                                    {{ $shopGroup['coupon']['message'] ?? '' }}
                                                </span>
                                            </div>
                                            <div class="cart-shop-total-area">
                                                <div class="lp-shop-totals cart-shop-totals">
                                                    <div class="cart-shop-total-row">
                                                        <span class="text-secondary">Shop subtotal</span>
                                                        <strong data-shop-subtotal="{{ $shopGroup['shop_id'] }}">{{ $shopGroup['subtotal'] }}</strong>
                                                    </div>
                                                    <div class="cart-shop-total-row is-savings" @if (($shopGroup['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                                        <span>Offer savings - {{ $shopGroup['promotion_discount'] }}</span>
                                                        <span class="text-secondary">Shipping at checkout</span>
                                                    </div>
                                                    <p
                                                        class="text-caption-01 text-danger mb-0"
                                                        data-shop-delivery-minimum="{{ $shopGroup['shop_id'] }}"
                                                        {{ empty($shopGroup['delivery_minimum']['message']) ? 'hidden' : '' }}>
                                                        {{ $shopGroup['delivery_minimum']['message'] ?? '' }}
                                                    </p>
                                                </div>
                                                <div class="cart-shop-checkout lp-shop-checkout">
                                                    <a href="{{ route('storefront.checkout') }}"
                                                        class="lp-btn-dark">
                                                        <span class="fw-semibold">Proceed to checkout</span>
                                                        <i class="icon icon-CaretRightThin" style="font-size:12px;"></i>
                                                    </a>
                                                    <p class="cart-shop-checkout-note">
                                                        Checkout is per shop. Choose a shop to continue.<br>
                                                        You'll checkout items from <span class="fw-medium" style="color:#111;">{{ $shopGroup['shop_name'] }}</span> only. Other shop items stay saved.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                            <div class="lp-secure-strip">
                                <i class="icon icon-Lock"></i> Secure cart &middot; Promotions calculated live
                            </div>
                        </div>
                     
                </div>

                <div class="col-lg-4">
                    <div class="fl-sidebar-cart mt-lg-0 sticky-top" style="top: 80px;">
                        <div class="box-order-summary ws-card">
                            <div class="cart-summary-card">
                                <div class="cart-summary-title">Order Summary</div>
                                <div class="cart-summary-subtitle">Totals update as you change quantities or coupons.</div>
                                <div class="mt-4 d-flex flex-column gap-2">
                                    <div class="cart-summary-row mb-0">
                                        <span>Subtotal ({{ $cartLineCount }} {{ Str::plural('item', $cartLineCount) }})</span>
                                        <span class="fw-semibold text-dark" data-cart-subtotal>{{ $cart['base_subtotal'] ?? $cart['subtotal'] }}</span>
                                    </div>
                                    <div class="cart-summary-row is-savings mb-0" data-cart-summary-savings-row @if (($cart['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                        <span><i class="icon icon-Tag" style="font-size:11px;"></i>Offer Savings</span>
                                        <span class="fw-semibold" data-cart-promotion-discount>{{ $cart['promotion_discount'] }}</span>
                                    </div>
                                    <div class="cart-summary-row mb-0">
                                        <span>Shipping</span>
                                        <span style="font-size: 12px;">Calculated at checkout</span>
                                    </div>
                                    <hr class="cart-summary-divider">
                                    <div class="cart-summary-row is-total align-items-baseline">
                                        <span>Total</span>
                                        <span data-cart-total>{{ $cart['total'] }}</span>
                                    </div>
                                    <span class="lp-summary-save" data-cart-summary-savings-note @if (($cart['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                        <span aria-hidden="true">₹</span>
                                        You save <span data-cart-promotion-discount-note>{{ ltrim($cart['promotion_discount'] ?? '', '-') }}</span> on this cart
                                    </span>
                                </div>
                                <div class="cart-summary-actions">
                                    <p class="cart-summary-action-note">
                                        @if ($isSingleShopCart)
                                            Checkout is ready for this shop.
                                        @else
                                            Checkout is per shop. Choose a shop to continue.
                                        @endif
                                    </p>
                                    @if ($isSingleShopCart)
                                        <a href="{{ route('storefront.checkout') }}"
                                            class="lp-btn-dark cart-summary-cta w-100 action-checkout">
                                            <span class="fw-semibold">Proceed To Checkout</span>
                                        </a>
                                    @else
                                        <a href="{{ route('storefront.products') }}" class="lp-btn-dark cart-summary-cta w-100">
                                            Continue Shopping &rarr;
                                        </a>
                                    @endif
                                    <p class="cart-summary-secondary-note">
                                        @if ($isSingleShopCart)
                                            Or <a href="{{ route('storefront.products') }}" class="link">Continue Shopping</a>
                                        @else
                                            Or use the <span class="fw-medium">Proceed to checkout</span> button inside each shop card.
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="cart-summary-trust text-caption-01 px-4 pb-3">
                                <span>Secure</span><span>&middot;</span><span>Easy exchanges</span><span>&middot;</span><span>Cards &amp; UPI</span>
                            </div>
                        </div>
                        <div class="ws-card lp-help-card mt-3">
                            <div class="fw-semibold">Need help?</div>
                            <div class="text-caption-01 cl-text-3 mt-1">Coupons are shop-specific. Apply a code inside the shop you want to checkout.</div>
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

            const positiveMoney = (value) => String(value || '').replace(/^-/, '');

            const offerSource = (promotion) => {
                if (!promotion || typeof promotion !== 'object') {
                    return '';
                }

                if (promotion.activation_type === 'coupon' && promotion.coupon_code) {
                    return `Coupon: ${promotion.coupon_code}`;
                }

                return 'Applied automatically';
            };

            const offerIconClass = (promotion) => {
                if (promotion?.activation_type === 'coupon') {
                    return 'is-coupon';
                }

                return promotion?.reward_type === 'fixed_discount' ? 'is-blue' : 'is-auto';
            };

            const offerIconMarkup = (promotion) => {
                const iconClass = offerIconClass(promotion);

                if (iconClass === 'is-auto') {
                    return '<i class="fas fa-gift" aria-hidden="true"></i>';
                }

                return iconClass === 'is-blue' ? '&#8377;' : '%';
            };

            const syncCart = (payload) => {
                document.querySelectorAll('[data-storefront-cart-count]').forEach((count) => {
                    count.textContent = payload.cart_count || '0';
                });
                window.WindowShopMiniCart?.sync?.(payload);

                const subtotal = page.querySelector('[data-cart-subtotal]');
                const total = page.querySelector('[data-cart-total]');
                const promotionDiscount = page.querySelector('[data-cart-promotion-discount]');
                const promotionDiscountNote = page.querySelector('[data-cart-promotion-discount-note]');
                const savingsRow = page.querySelector('[data-cart-summary-savings-row]');
                const savingsNote = page.querySelector('[data-cart-summary-savings-note]');
                const promotionDiscountCents = Number(payload.promotion_discount_cents || 0);

                if (subtotal) {
                    subtotal.textContent = payload.base_subtotal || payload.subtotal || 'INR 0.00';
                }

                if (total) {
                    total.textContent = payload.total || 'INR 0.00';
                }

                if (promotionDiscount) {
                    promotionDiscount.textContent = payload.promotion_discount || 'INR 0.00';
                }

                if (promotionDiscountNote) {
                    promotionDiscountNote.textContent = positiveMoney(payload.promotion_discount || '');
                }

                if (savingsRow) {
                    savingsRow.hidden = promotionDiscountCents <= 0;
                }

                if (savingsNote) {
                    savingsNote.hidden = promotionDiscountCents <= 0;
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

                    const minimumMessage = page.querySelector(`[data-shop-delivery-minimum="${shop.shop_id}"]`);
                    if (minimumMessage) {
                        const message = shop.delivery_minimum?.message || '';
                        minimumMessage.textContent = message;
                        minimumMessage.hidden = message === '';
                    }

                    const couponWrap = page.querySelector(`[data-shop-coupon="${shop.shop_id}"]`);
                    if (couponWrap) {
                        const coupon = shop.coupon || {};
                        const input = couponWrap.querySelector('[data-coupon-input]');
                        const message = couponWrap.querySelector('[data-coupon-message]');
                        const remove = couponWrap.querySelector('[data-coupon-remove-url]');
                        const appliedCode = couponWrap.querySelector('[data-coupon-applied-code]');
                        const appliedCodeText = couponWrap.querySelector('[data-coupon-code-display]');
                        const unapplied = couponWrap.querySelector('.coupon-unapplied');
                        const hasCouponCode = Boolean(coupon.code);

                        if (input) {
                            input.value = coupon.code || '';
                        }

                        if (message) {
                            message.textContent = coupon.message || '';
                            message.classList.toggle('text-success', ['applied', 'guest_verification_required'].includes(coupon.status || ''));
                            message.classList.toggle('text-danger', ['invalid', 'expired', 'inactive', 'not_started', 'not_eligible', 'unsupported_reward_type'].includes(coupon.status || ''));
                            message.classList.toggle('cl-text-2', !message.classList.contains('text-success') && !message.classList.contains('text-danger'));
                        }

                        if (remove) {
                            remove.hidden = !hasCouponCode;
                        }

                        if (unapplied) {
                            unapplied.hidden = hasCouponCode;
                        }

                        if (appliedCode) {
                            appliedCode.hidden = !hasCouponCode;
                        }

                        if (appliedCodeText) {
                            appliedCodeText.textContent = coupon.code || '';
                        }
                    }

                    (shop.items || []).forEach((item) => {
                        const row = page.querySelector(`[data-cart-item="${item.id}"]`);

                        if (!row) {
                            return;
                        }

                        const input = row.querySelector('[data-cart-quantity-input]');
                        const price = row.querySelector('[data-cart-item-price]');
                        const line = row.querySelector('[data-cart-item-subtotal]');
                        const discount = row.querySelector('[data-cart-item-promotion-discount]');
                        const baseSubtotal = row.querySelector('[data-cart-item-base-subtotal]');
                        const offer = row.querySelector('[data-cart-item-offer]');
                        const offerIcon = row.querySelector('[data-cart-item-offer-icon]');
                        const offerName = row.querySelector('[data-cart-item-offer-name]');
                        const offerSourceNode = row.querySelector('[data-cart-item-offer-source]');
                        const offerSavings = row.querySelector('[data-cart-item-offer-savings]');
                        const discountCents = Number(item.promotion_discount_cents || 0);
                        const hasOffer = Boolean(item.promotion) && (discountCents > 0 || item.is_generated_gift);

                        if (input) {
                            input.value = item.quantity;
                        }

                        if (price) {
                            price.textContent = item.unit_price;
                        }

                        if (line) {
                            line.textContent = item.is_generated_gift ? `FREE / ${item.line_subtotal}` : item.line_subtotal;
                        }

                        if (discount) {
                            discount.textContent = discountCents > 0 ? `You save ${positiveMoney(item.promotion_discount)}` : '';
                            discount.hidden = discountCents <= 0;
                        }

                        if (baseSubtotal) {
                            baseSubtotal.textContent = item.base_line_subtotal || '';
                            baseSubtotal.hidden = discountCents <= 0;
                        }

                        if (offer) {
                            offer.hidden = !hasOffer;
                            offer.classList.toggle('is-coupon', offerIconClass(item.promotion) === 'is-coupon');
                            offer.classList.toggle('is-blue', offerIconClass(item.promotion) === 'is-blue');
                        }

                        if (offerIcon) {
                            const nextIconClass = offerIconClass(item.promotion);
                            offerIcon.className = `lp-offer-icon ${nextIconClass}`;
                            offerIcon.innerHTML = offerIconMarkup(item.promotion);
                        }

                        if (offerName) {
                            offerName.textContent = item.promotion?.name ? `Offer: ${item.promotion.name}` : '';
                        }

                        if (offerSourceNode) {
                            offerSourceNode.textContent = offerSource(item.promotion);
                        }

                        if (offerSavings) {
                            offerSavings.textContent = discountCents > 0 ? `You save ${positiveMoney(item.promotion_discount)}` : '';
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
                    page.querySelector(`[data-cart-shop-card="${shopId}"]`)?.remove();
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

            page.querySelectorAll('[data-coupon-apply-form]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const button = form.querySelector('button[type="submit"]');
                    const body = new URLSearchParams(new FormData(form));

                    try {
                        if (button) {
                            button.disabled = true;
                        }

                        const payload = await requestCart(form.action, 'POST', body);
                        syncCart(payload);
                    } catch (error) {
                        const wrap = form.closest('[data-shop-coupon]');
                        const message = wrap?.querySelector('[data-coupon-message]');
                        if (message) {
                            message.textContent = error.message;
                            message.classList.add('text-danger');
                            message.classList.remove('text-success', 'cl-text-2');
                        }
                    } finally {
                        if (button) {
                            button.disabled = false;
                        }
                    }
                });
            });

            page.querySelectorAll('[data-coupon-remove-url]').forEach((button) => {
                button.addEventListener('click', async () => {
                    try {
                        button.disabled = true;
                        const payload = await requestCart(button.dataset.couponRemoveUrl, 'DELETE');
                        syncCart(payload);
                    } catch (error) {
                        const wrap = button.closest('[data-shop-coupon]');
                        const message = wrap?.querySelector('[data-coupon-message]');
                        if (message) {
                            message.textContent = error.message;
                            message.classList.add('text-danger');
                            message.classList.remove('text-success', 'cl-text-2');
                        }
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        });
    </script>
@endpush
