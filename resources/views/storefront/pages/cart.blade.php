@extends('storefront.layouts.app')

@section('title', 'Shopping Cart | WindowShop')
@section('meta_description', 'Review your selected local shop products before checkout on WindowShop.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/admin/icons/fontawesome/styles.min.css') }}">

    <style>
        .fa-solid,
        .fa-regular {
            display: inline-block;
            min-width: 1em;
            font-style: normal;
            font-variant: normal;
            line-height: 1;
            text-rendering: auto;
            vertical-align: middle;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .fa-solid {
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
        }

        .fa-regular {
            font-family: "Font Awesome 5 Free";
            font-weight: 400;
        }

        .fa-trash-can::before {
            content: "\f2ed";
        }

        .fa-circle-check::before {
            content: "\f058";
        }

        .fa-shield-halved::before {
            content: "\f3ed";
        }

        .fa-rotate-left::before {
            content: "\f2ea";
        }

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
        .cart-shop-item,
        .product-row {
            display: flex;
            gap: 12px;
            padding: 14px 12px;
            border-bottom: 1px solid var(--line);
        }

        @media (min-width: 576px) {
            .cart-shop-item,
            .product-row {
                gap: 16px;
                padding: 20px 24px;
            }
        }

        .cart-shop-item:last-child,
        .product-row:last-child {
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

        .cart-shop-item.is-unavailable .ws-img,
        .product-row.is-unavailable .ws-img {
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

        .ws-offer-coupon,
        .ws-offer-auto,
        .ws-offer-blue {
            border-radius: 8px;
            border: 1px solid;
            max-width: 100%;
        }

        .ws-offer-coupon {
            background: #FFF7ED;
            border-color: #FDBA7466;
        }

        .ws-offer-auto {
            background: #ECFDF5;
            border-color: #A7F3D0;
        }

        .ws-offer-blue {
            background: #EFF6FF;
            border-color: #BFDBFE;
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

        .ws-qty {
            display: grid;
            grid-template-columns: 32px 32px 32px;
            align-items: center;
            justify-content: center;
            width: 96px;
            height: 32px;
            border: 1px solid var(--line);
            border-radius: 999px;
            overflow: hidden;
            background: #fff;
            flex: 0 0 96px;
        }

        .ws-qty button {
            width: 32px;
            height: 32px;
            border: 0;
            padding: 0;
            background: transparent;
            color: #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 14px;
        }

        .ws-qty .qty-value {
            width: 32px;
            height: 32px;
            border: 0;
            padding: 0;
            background: transparent;
            color: #111;
            font-size: 14px;
            line-height: 32px;
            text-align: center;
            display: block;
        }

        .ws-qty .fa-solid {
            min-width: auto;
            font-size: 10px;
            line-height: 1;
            top: 0;
        }

        .btn-ws-dark,
        .btn-ws-primary {
            border-radius: 999px;
            font-weight: 600;
        }

        .btn-ws-dark {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }

        .btn-ws-primary {
            background: #111;
            border-color: #111;
            color: #fff;
        }

        .btn-ws-dark:hover,
        .btn-ws-primary:hover {
            background: #000;
            border-color: #000;
            color: #fff;
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
        $effectiveUnitPrice = static function (array $item): string {
            $quantity = (float) ($item['quantity_value'] ?? $item['quantity'] ?? 1);
            $quantity = $quantity > 0 ? $quantity : 1;
            $lineSubtotalCents = (int) ($item['line_subtotal_cents'] ?? $item['unit_price_cents'] ?? 0);

            return '₹'.number_format(($lineSubtotalCents / 100) / $quantity, 2);
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
                     
                        <div class="d-flex flex-column gap-4" id="shop-groups" data-cart-items>
                            @foreach ($shopGroups as $shopGroup)
                                @php
                                    $shopItems = collect($shopGroup['items'] ?? []);
                                    $paidItems = $shopItems->reject(fn (array $item): bool => ! empty($item['is_generated_gift']));
                                    $shopOfferCount = collect($shopGroup['applied_promotions'] ?? [])->count();
                                @endphp
                                <section class="shop-group ws-card" data-shop="{{ $shopGroup['shop_id'] }}" data-cart-shop-card="{{ $shopGroup['shop_id'] }}">
                                    <div class="ws-shop-head bg-white d-flex align-items-center justify-content-between px-3 px-sm-4 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;{{ $avatarStyle($shopGroup['shop_name']) }}font-size:12px;">{{ $initials($shopGroup['shop_name']) }}</span>
                                            <div>
                                                <div class="fw-semibold" style="font-size:15px; color:#111; line-height:1;">{{ $shopGroup['shop_name'] }}</div>
                                                <div class="small text-secondary">
                                                    <span>{{ $paidItems->count() }} {{ Str::plural('item', $paidItems->count()) }} &middot; {{ $shopOfferCount > 0 ? 'Eligible for offers' : 'Try a coupon' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        @if ($shopOfferCount > 0)
                                            <span class="badge rounded-pill border fw-medium d-none d-sm-inline-flex align-items-center gap-1" style="background:#ECFDF5;color:#065F46;border-color:#A7F3D0 !important;">
                                                <i class="fa-solid fa-tag" style="font-size:10px;"></i>
                                                {{ $shopOfferCount }} {{ Str::plural('offer', $shopOfferCount) }} applied
                                            </span>
                                        @else
                                            <span class="badge rounded-pill border d-none d-sm-inline-flex align-items-center gap-1" style="background:#FFFBEB;color:#92400E;border-color:#FDE68A !important;">
                                                <i class="fa-regular fa-lightbulb" style="font-size:10px;"></i>
                                                Try a coupon
                                            </span>
                                        @endif
                                    </div>

                                    <div data-cart-shop-items="{{ $shopGroup['shop_id'] }}">
                                        @foreach ($shopGroup['items'] as $item)
                                        @php
                                            $promotion = is_array($item['promotion'] ?? null) ? $item['promotion'] : null;
                                            $offerSource = $offerMeta($promotion);
                                            $iconClass = $offerIconClass($promotion);
                                            $hasOffer = ! empty($promotion) && (($item['promotion_discount_cents'] ?? 0) > 0 || ! empty($item['is_generated_gift']));
                                            $isGift = ! empty($item['is_generated_gift']);
                                                $isCouponBacked = ($promotion['activation_type'] ?? null) === 'coupon' && filled($promotion['coupon_code'] ?? null);
                                            $offerBoxClass = $isCouponBacked ? 'ws-offer-coupon' : ($iconClass === 'is-blue' ? 'ws-offer-blue' : 'ws-offer-auto');
                                            $offerIconColor = $isCouponBacked ? '#F97316' : ($iconClass === 'is-blue' ? '#2563EB' : '#059669');
                                            $offerTextColor = $isCouponBacked ? '#9A3412' : ($iconClass === 'is-blue' ? '#1E40AF' : '#065F46');
                                            $offerMetaColor = $isCouponBacked ? '#C2410C' : ($iconClass === 'is-blue' ? '#2563EB' : '#047857');
                                            @endphp
                                            @if ($isGift)
                                                <div class="ws-gift d-flex align-items-center gap-3 px-3 px-sm-4 py-3"
                                                    data-cart-item="{{ $item['id'] }}"
                                                    data-cart-shop="{{ $shopGroup['shop_id'] }}"
                                                    data-cart-generated-gift="1">
                                                    <div class="position-relative flex-shrink-0" style="padding:8px;">
                                                        <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" class="ws-img" style="border-color:#FDE68A;" loading="lazy">
                                                        <span class="position-absolute badge rounded-pill bg-dark border border-white shadow-sm" style="font-size:9px; letter-spacing:.12em; top:14px; left:14px; padding:5px 9px; line-height:1;">FREE GIFT</span>
                                                    </div>
                                                    <div class="flex-grow-1" style="min-width:0;">
                                                        <div class="fw-bold text-xxs" style="letter-spacing:.12em; color:#92400E;">FREE GIFT</div>
                                                        <a href="{{ $item['product_url'] }}" class="fw-medium link" style="font-size:14px; color:#111;">{{ $item['product_name'] }}</a>
                                                        <div class="small text-secondary">
                                                            Regular value <span class="text-decoration-line-through" data-cart-gift-unit-price>{{ $item['unit_price'] }}</span>
                                                            &middot; <span class="fw-bold text-success">FREE / <span data-cart-item-subtotal>{{ $item['line_subtotal'] }}</span></span>
                                                        </div>
                                                        <div class="small text-secondary mt-1">
                                                            Free gift qty: <span class="fw-semibold text-success" data-cart-gift-quantity>{{ $item['quantity'] }}</span>
                                                        </div>
                                                        <div class="text-xxs text-secondary mt-1">
                                                            Added by: <span class="fw-medium">{{ $promotion['name'] ?? 'offer' }}</span>@if ($isCouponBacked)
                                                                &middot; Coupon: <span class="mono fw-bold">{{ $promotion['coupon_code'] }}</span>
                                                            @endif
                                                        </div>
                                                        <p class="text-caption-01 mt-2 mb-0" data-cart-item-message role="status" hidden></p>
                                                    </div>
                                                    <span class="d-none d-sm-inline-flex badge bg-white text-secondary border fw-semibold align-items-center gap-1" style="font-size:11px;">
                                                        <i class="fa-solid fa-lock" style="font-size:10px;"></i>
                                                        Gift &mdash; no changes
                                                    </span>
                                                </div>
                                            @else
                                                <div class="product-row d-flex gap-3 p-3 p-sm-4 border-bottom {{ $item['is_available'] ? '' : 'is-unavailable' }}"
                                                    data-price="{{ ($item['base_line_subtotal_cents'] ?? $item['line_subtotal_cents'] ?? 0) / 100 }}"
                                                    data-final="{{ ($item['line_subtotal_cents'] ?? 0) / 100 }}"
                                                    data-qty="{{ $item['quantity_value'] ?? $item['quantity'] }}"
                                                    data-cart-item="{{ $item['id'] }}"
                                                    data-cart-shop="{{ $shopGroup['shop_id'] }}">
                                                    <img loading="lazy" width="100" height="133"
                                                        src="{{ $item['image'] }}"
                                                        alt="{{ $item['product_name'] }}"
                                                        class="ws-img flex-shrink-0">
                                                    <div class="flex-grow-1" style="min-width:0;">
                                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                                            <div style="min-width:0;">
                                                                <div class="fw-medium text-truncate"
                                                                    style="font-size:14px; color:#111;">
                                                                    <a href="{{ $item['product_url'] }}" class="name fw-medium link text-line-clamp-1">{{ $item['product_name'] }}</a>
                                                                </div>
                                                                @if (! empty($item['attributes']))
                                                                    <div class="small text-secondary">
                                                                        @foreach ($item['attributes'] as $attribute)
                                                                            <span>{{ $attribute['label'] }}: {{ $attribute['value'] }}</span>@if (! $loop->last) <span>&middot;</span> @endif
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                                <div class="{{ $offerBoxClass }} d-inline-flex align-items-center gap-2 px-2 py-2 mt-2"
                                                                    data-cart-item-offer
                                                                    @if (! $hasOffer) hidden @endif>
                                                                    <span class="d-flex align-items-center justify-content-center text-white rounded" style="width:24px;height:24px;background:{{ $offerIconColor }};font-size:10px;" data-cart-item-offer-icon>
                                                                        @if ($iconClass === 'is-auto')
                                                                            <i class="fa-solid fa-gift" aria-hidden="true"></i>
                                                                        @else
                                                                            {{ $offerIconGlyph($promotion) }}
                                                                        @endif
                                                                    </span>
                                                                    <span style="line-height:1.2;">
                                                                        <span class="fw-semibold d-block" style="font-size:12px; color:{{ $offerTextColor }};" data-cart-item-offer-name>
                                                                            @if ($promotion)
                                                                                Offer: {{ $promotion['name'] }}
                                                                            @endif
                                                                        </span>
                                                                        <span style="font-size:11px; color:{{ $offerMetaColor }};">
                                                                            <span data-cart-item-offer-source>{{ $offerSource }}</span>
                                                                            <span>&middot;</span>
                                                                            <span data-cart-item-offer-savings>
                                                                                @if (($item['promotion_discount_cents'] ?? 0) > 0)
                                                                                    You save {{ ltrim($item['promotion_discount'], '-') }}
                                                                                @endif
                                                                            </span>
                                                                        </span>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <button type="button"
                                                                class="remove-btn btn btn-link btn-sm text-decoration-none d-none d-sm-inline-flex text-secondary p-0 flex-shrink-0 cart_remove remove"
                                                                data-cart-remove-url="{{ $item['remove_url'] }}">
                                                                <i class="fa-regular fa-trash-can me-1"></i>Remove
                                                            </button>
                                                        </div>
                                                        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mt-3">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="ws-qty" data-cart-title="Quantity">
                                                                    <button type="button"
                                                                        class="qty-minus"
                                                                        data-cart-quantity-step="-1"
                                                                        {{ $item['is_available'] ? '' : 'disabled' }}>
                                                                        <i class="fa-solid fa-minus" style="font-size:10px;"></i>
                                                                    </button>
                                                                    <span class="qty-value text-center fw-semibold" style="width:32px; font-size:14px;" data-cart-quantity-display>{{ $item['quantity'] }}</span>
                                                                    <input class="quantity-product visually-hidden" type="hidden" name="quantity"
                                                                        value="{{ $item['quantity'] }}"
                                                                        data-cart-quantity-input
                                                                        data-cart-update-url="{{ $item['update_url'] }}">
                                                                    <button type="button"
                                                                        class="qty-plus"
                                                                        data-cart-quantity-step="1"
                                                                        {{ $item['is_available'] ? '' : 'disabled' }}>
                                                                        <i class="fa-solid fa-plus" style="font-size:10px;"></i>
                                                                    </button>
                                                                </div>
                                                                @if (! empty($item['return_exchange_policy']))
                                                                    <span class="d-none d-sm-inline small text-secondary cart-product-policy">
                                                                        {{ $item['return_exchange_policy']['inline'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <div class="text-end ms-auto">
                                                                <div class="cart-total-stack" data-cart-title="Total Price">
                                                                    <div class="d-flex align-items-center gap-2 justify-content-end">
                                                                        <span class="cart-base-subtotal"
                                                                            data-cart-item-base-subtotal
                                                                            @if (($item['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                                                            {{ $item['base_line_subtotal'] ?? '' }}
                                                                        </span>
                                                                        <span class="fw-bold cart_total" style="font-size:15px; color:#111;" data-cart-item-subtotal>{{ $item['line_subtotal'] }}</span>
                                                                    </div>
                                                                    <div class="small text-secondary" data-cart-title="Price">
                                                                        <span data-cart-item-price>{{ $effectiveUnitPrice($item) }}</span> each
                                                                    </div>
                                                                    <div class="text-success fw-medium"
                                                                        style="font-size:11px;"
                                                                        data-cart-item-promotion-discount
                                                                        @if (($item['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                                                        {{ ($item['promotion_discount_cents'] ?? 0) > 0 ? 'You save '.ltrim($item['promotion_discount'], '-') : '' }}
                                                                    </div>
                                                                    @if (! empty($item['return_exchange_policy']))
                                                                        <div class="d-sm-none small text-secondary cart-product-policy">
                                                                            {{ $item['return_exchange_policy']['inline'] }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button type="button"
                                                            class="remove-btn btn btn-link btn-sm text-decoration-none d-sm-none text-secondary p-0 mt-2 cart_remove remove"
                                                            data-cart-remove-url="{{ $item['remove_url'] }}">
                                                            <i class="fa-regular fa-trash-can me-1"></i>Remove
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

                                    <div class="ws-coupon-box px-3 px-sm-4 py-3" data-shop-coupon="{{ $shopGroup['shop_id'] }}" data-cart-shop-coupon-row="{{ $shopGroup['shop_id'] }}">
                                            @php
                                                $couponStatus = $shopGroup['coupon']['status'] ?? '';
                                                $couponIsApplied = ! empty($shopGroup['coupon']['won'])
                                                    || in_array($couponStatus, ['applied', 'guest_verification_required'], true);
                                                $couponIsInvalid = in_array($couponStatus, ['invalid', 'expired', 'inactive', 'not_started', 'not_eligible', 'unsupported_reward_type'], true);
                                            @endphp
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="fa-solid fa-tag text-secondary" style="font-size:12px;"></i>
                                                <span class="small fw-semibold text-secondary" style="letter-spacing:.06em; text-transform:uppercase; font-size:11px;">Coupon for this shop</span>
                                            </div>
                                            <form method="POST"
                                                action="{{ route('storefront.cart.shops.coupon.store', ['shop' => $shopGroup['shop_id']]) }}"
                                                class="coupon-unapplied d-flex gap-2 mb-0"
                                                data-coupon-apply-form
                                                {{ $couponIsApplied ? 'hidden' : '' }}>
                                                @csrf
                                                <div class="flex-grow-1 position-relative">
                                                    <input
                                                        name="coupon_code"
                                                        class="coupon-input form-control rounded-pill bg-white ps-3 pe-5"
                                                        style="height:40px; font-size:14px;"
                                                        value="{{ $shopGroup['coupon']['code'] ?? '' }}"
                                                        placeholder="Enter coupon code"
                                                        data-coupon-input>
                                                    <i class="fa-regular fa-keyboard position-absolute top-50 end-0 translate-middle-y me-3 text-secondary opacity-50" style="font-size:12px;"></i>
                                                </div>
                                                <button type="submit" class="coupon-apply-btn btn btn-ws-dark px-4" style="height:40px;">
                                                    Apply
                                                </button>
                                            </form>
                                            <div class="coupon-applied d-flex align-items-center justify-content-between bg-white border border-success-subtle rounded-pill px-3 py-2"
                                                data-coupon-applied-code
                                                {{ $couponIsApplied ? '' : 'hidden' }}>
                                                <span class="small">
                                                    <span class="text-secondary">Coupon</span>
                                                    <span class="mono fw-bold text-success coupon-code-display" data-coupon-code-display>{{ $shopGroup['coupon']['code'] ?? '' }}</span>
                                                    <span class="text-success fw-medium">applied</span>
                                                    <i class="fa-solid fa-circle-check text-success ms-1"></i>
                                                </span>
                                                <button type="button"
                                                    class="coupon-remove-btn btn btn-link btn-sm text-secondary p-0 text-decoration-underline"
                                                    style="font-size:12px;"
                                                    data-coupon-remove-url="{{ route('storefront.cart.shops.coupon.destroy', ['shop' => $shopGroup['shop_id']]) }}"
                                                    {{ $couponIsApplied ? '' : 'hidden' }}>
                                                    Remove
                                                </button>
                                            </div>
                                            <div class="coupon-feedback small mt-2">
                                                <span class="text-caption-01 {{ $couponIsApplied ? 'text-success' : ($couponIsInvalid ? 'text-danger' : 'cl-text-2') }}"
                                                    data-coupon-message>
                                                    {{ $shopGroup['coupon']['message'] ?? '' }}
                                                </span>
                                            </div>
                                            <div>
                                                <div>
                                                    <div class="d-flex justify-content-between small mt-3">
                                                        <span class="text-secondary">Shop subtotal</span>
                                                        <span class="fw-semibold shop-subtotal" data-shop-subtotal="{{ $shopGroup['shop_id'] }}">{{ $shopGroup['subtotal'] }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between text-xxs mt-1" @if (($shopGroup['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                                        <span class="text-success fw-medium shop-savings">Offer savings &mdash; {{ $shopGroup['promotion_discount'] }}</span>
                                                        <span class="text-secondary">Shipping at checkout</span>
                                                    </div>
                                                    <p
                                                        class="text-caption-01 text-danger mb-0 mt-2"
                                                        data-shop-delivery-minimum="{{ $shopGroup['shop_id'] }}"
                                                        {{ empty($shopGroup['delivery_minimum']['message']) ? 'hidden' : '' }}>
                                                        {{ $shopGroup['delivery_minimum']['message'] ?? '' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <a href="{{ route('storefront.checkout') }}"
                                                        class="btn btn-ws-primary w-100 d-flex align-items-center justify-content-center gap-2 mt-3 px-4"
                                                        style="height:42px;">
                                                        Proceed to checkout <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i>
                                                    </a>
                                                    <div class="text-xxs text-secondary mt-2 text-center" style="line-height:1.45;">
                                                        Checkout is per shop. Choose a shop to continue.<br>
                                                        You'll checkout items from <span class="fw-medium" style="color:#111;">{{ $shopGroup['shop_name'] }}</span> only. Other shop items stay saved.
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                                </section>
                            @endforeach
                            <div class="d-flex align-items-center justify-content-center gap-2 small text-secondary py-2">
                                <i class="fa-solid fa-shield-halved text-secondary"></i> Secure cart &middot; Promotions calculated live
                            </div>
                        </div>
                     
                </div>

                <div class="col-lg-4">
                    <div class="fl-sidebar-cart mt-lg-0">
                        <div class="ws-card position-sticky" style="top:80px;">
                            <div class="p-4">
                                <div class="fw-semibold" style="font-size:15px; color:#111;">Order Summary</div>
                                <div class="small text-secondary">Totals update as you change quantities or coupons.</div>
                                <div class="mt-4 d-flex flex-column gap-2 small">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-secondary">Subtotal <span class="text-secondary opacity-75">({{ $cartLineCount }} {{ Str::plural('item', $cartLineCount) }})</span></span>
                                        <span class="fw-medium" data-cart-subtotal>{{ $cart['base_subtotal'] ?? $cart['subtotal'] }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between text-success" data-cart-summary-savings-row @if (($cart['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                        <span class="d-flex align-items-center gap-1"><i class="fa-solid fa-tag" style="font-size:11px;"></i> Offer Savings</span>
                                        <span class="fw-semibold" data-cart-promotion-discount>{{ $cart['promotion_discount'] }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between text-secondary">
                                        <span>Shipping</span>
                                        <span style="font-size: 12px;">Calculated at checkout</span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <span class="fw-semibold" style="color:#111;">Total</span>
                                        <span class="fw-bold" style="font-size:20px; color:#111;" data-cart-total>{{ $cart['total'] }}</span>
                                    </div>
                                    <span class="badge rounded-pill border d-inline-flex align-items-center gap-2 align-self-start px-3 py-2" style="background:#ECFDF5; color:#065F46; border-color:#A7F3D0 !important; font-size:12px;" data-cart-summary-savings-note @if (($cart['promotion_discount_cents'] ?? 0) <= 0) hidden @endif>
                                        <i class="fa-solid fa-piggy-bank" style="font-size:11px;"></i>
                                        You save <span data-cart-promotion-discount-note>{{ ltrim($cart['promotion_discount'] ?? '', '-') }}</span> on this cart
                                    </span>
                                </div>
                                <div class="mt-4 d-flex flex-column gap-2">
                                    <div class="text-center" style="font-size:11px; color:#6B7280;">
                                        @if ($isSingleShopCart)
                                            Checkout is ready for this shop.
                                        @else
                                            Checkout is per shop. Choose a shop to continue.
                                        @endif
                                    </div>
                                    @if ($isSingleShopCart)
                                        <a href="{{ route('storefront.checkout') }}"
                                            class="btn btn-ws-dark w-100 d-flex align-items-center justify-content-center gap-2 action-checkout"
                                            style="height:44px;">
                                            Proceed To Checkout <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('storefront.products') }}" class="btn btn-ws-dark w-100 d-flex align-items-center justify-content-center gap-2" style="height:44px;">
                                            Continue Shopping <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i>
                                        </a>
                                    @endif
                                    <div class="text-center" style="font-size:11px; color:#9CA3AF;">
                                        @if ($isSingleShopCart)
                                            Or <a href="{{ route('storefront.products') }}" class="link fw-medium text-secondary">Continue Shopping</a>
                                        @else
                                            Or use the <span class="fw-medium text-secondary">Proceed to checkout</span> button inside each shop card.
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-center gap-3 small text-secondary border-top px-4 py-3" style="background:#FAFAFA; font-size:11px;">
                                <span><i class="fa-solid fa-lock me-1"></i>Secure</span><span class="rounded-circle bg-secondary opacity-25" style="width:4px;height:4px;"></span><span><i class="fa-solid fa-rotate-left me-1"></i>Easy exchanges</span><span class="rounded-circle bg-secondary opacity-25" style="width:4px;height:4px;"></span><span><i class="fa-regular fa-credit-card me-1"></i>Cards &amp; UPI</span>
                            </div>
                        </div>
                        <div class="ws-card mt-3 p-3 text-center border-dashed" style="border-style:dashed !important;">
                            <div class="small fw-semibold" style="color:#374151;">Need help?</div>
                            <div class="small text-secondary mt-1">Coupons are shop-specific. Apply a code inside the shop you want to checkout.</div>
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
            const formatCartMoney = (cents) => `₹${(Number(cents || 0) / 100).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;

            const effectiveUnitPrice = (item) => {
                const quantity = Number(item?.quantity_value || item?.quantity || 1);
                const lineSubtotalCents = Number(item?.line_subtotal_cents || 0);

                if (quantity > 0 && lineSubtotalCents >= 0) {
                    return formatCartMoney(lineSubtotalCents / quantity);
                }

                return item?.unit_price || formatCartMoney(0);
            };

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char]));

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
                    return '<i class="fa-solid fa-gift" aria-hidden="true"></i>';
                }

                return iconClass === 'is-blue' ? '&#8377;' : '%';
            };

            const offerBoxClass = (promotion) => {
                const iconClass = offerIconClass(promotion);

                if (iconClass === 'is-coupon') {
                    return 'ws-offer-coupon';
                }

                return iconClass === 'is-blue' ? 'ws-offer-blue' : 'ws-offer-auto';
            };

            const offerIconColor = (promotion) => {
                const iconClass = offerIconClass(promotion);

                if (iconClass === 'is-coupon') {
                    return '#F97316';
                }

                return iconClass === 'is-blue' ? '#2563EB' : '#059669';
            };

            const renderGiftRow = (item, shopId) => {
                const promotion = item.promotion || {};
                const coupon = promotion.activation_type === 'coupon' && promotion.coupon_code
                    ? ` &middot; Coupon: <span class="mono fw-bold">${escapeHtml(promotion.coupon_code)}</span>`
                    : '';
                const row = document.createElement('div');

                row.className = 'ws-gift d-flex align-items-center gap-3 px-3 px-sm-4 py-3';
                row.dataset.cartItem = item.id;
                row.dataset.cartShop = shopId;
                row.dataset.cartGeneratedGift = '1';
                row.innerHTML = `
                    <div class="position-relative flex-shrink-0" style="padding:8px;">
                        <img src="${escapeHtml(item.image || '')}" alt="${escapeHtml(item.product_name || 'Free Gift')}" class="ws-img" style="border-color:#FDE68A;" loading="lazy">
                        <span class="position-absolute badge rounded-pill bg-dark border border-white shadow-sm" style="font-size:9px; letter-spacing:.12em; top:14px; left:14px; padding:5px 9px; line-height:1;">FREE GIFT</span>
                    </div>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="fw-bold text-xxs" style="letter-spacing:.12em; color:#92400E;">FREE GIFT</div>
                        <a href="${escapeHtml(item.product_url || '#')}" class="fw-medium link" style="font-size:14px; color:#111;">${escapeHtml(item.product_name || 'Free Gift')}</a>
                        <div class="small text-secondary">
                            Regular value <span class="text-decoration-line-through" data-cart-gift-unit-price>${escapeHtml(item.unit_price || '')}</span>
                            &middot; <span class="fw-bold text-success">FREE / <span data-cart-item-subtotal>${escapeHtml(item.line_subtotal || '')}</span></span>
                        </div>
                        <div class="small text-secondary mt-1">
                            Free gift qty: <span class="fw-semibold text-success" data-cart-gift-quantity>${escapeHtml(item.quantity || '1')}</span>
                        </div>
                        <div class="text-xxs text-secondary mt-1">
                            Added by: <span class="fw-medium">${escapeHtml(promotion.name || 'offer')}</span>${coupon}
                        </div>
                        <p class="text-caption-01 mt-2 mb-0" data-cart-item-message role="status" hidden></p>
                    </div>
                    <span class="d-none d-sm-inline-flex badge bg-white text-secondary border fw-semibold align-items-center gap-1" style="font-size:11px;">
                        <i class="fa-solid fa-lock" style="font-size:10px;"></i>
                        Gift &mdash; no changes
                    </span>
                `;

                return row;
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
                        const couponIsApplied = Boolean(coupon.won) || ['applied', 'guest_verification_required'].includes(coupon.status || '');

                        if (input) {
                            input.value = coupon.code || '';
                        }

                        if (message) {
                            message.textContent = coupon.message || '';
                            message.classList.toggle('text-success', couponIsApplied);
                            message.classList.toggle('text-danger', ['invalid', 'expired', 'inactive', 'not_started', 'not_eligible', 'unsupported_reward_type'].includes(coupon.status || ''));
                            message.classList.toggle('cl-text-2', !message.classList.contains('text-success') && !message.classList.contains('text-danger'));
                        }

                        if (remove) {
                            remove.hidden = !couponIsApplied;
                        }

                        if (unapplied) {
                            unapplied.hidden = couponIsApplied;
                        }

                        if (appliedCode) {
                            appliedCode.hidden = !couponIsApplied;
                        }

                        if (appliedCodeText) {
                            appliedCodeText.textContent = coupon.code || '';
                        }
                    }

                    const shopItemsWrap = page.querySelector(`[data-cart-shop-items="${shop.shop_id}"]`);
                    const currentGiftIds = new Set((shop.items || [])
                        .filter((item) => item.is_generated_gift)
                        .map((item) => String(item.id)));

                    page.querySelectorAll(`[data-cart-generated-gift="1"][data-cart-shop="${shop.shop_id}"]`).forEach((giftRow) => {
                        if (!currentGiftIds.has(String(giftRow.dataset.cartItem))) {
                            giftRow.remove();
                        }
                    });

                    (shop.items || []).forEach((item) => {
                        let row = page.querySelector(`[data-cart-item="${item.id}"]`);

                        if (!row && item.is_generated_gift && shopItemsWrap) {
                            row = renderGiftRow(item, shop.shop_id);
                            shopItemsWrap.append(row);
                        }

                        if (!row) {
                            return;
                        }

                        const input = row.querySelector('[data-cart-quantity-input]');
                        const quantityDisplay = row.querySelector('[data-cart-quantity-display]');
                        const price = row.querySelector('[data-cart-item-price]');
                        const line = row.querySelector('[data-cart-item-subtotal]');
                        const discount = row.querySelector('[data-cart-item-promotion-discount]');
                        const baseSubtotal = row.querySelector('[data-cart-item-base-subtotal]');
                        const offer = row.querySelector('[data-cart-item-offer]');
                        const offerIcon = row.querySelector('[data-cart-item-offer-icon]');
                        const offerName = row.querySelector('[data-cart-item-offer-name]');
                        const offerSourceNode = row.querySelector('[data-cart-item-offer-source]');
                        const offerSavings = row.querySelector('[data-cart-item-offer-savings]');
                        const giftQuantity = row.querySelector('[data-cart-gift-quantity]');
                        const giftUnitPrice = row.querySelector('[data-cart-gift-unit-price]');
                        const discountCents = Number(item.promotion_discount_cents || 0);
                        const hasOffer = Boolean(item.promotion) && (discountCents > 0 || item.is_generated_gift);

                        if (input) {
                            input.value = item.quantity;
                        }

                        if (quantityDisplay) {
                            quantityDisplay.textContent = item.quantity;
                        }

                        if (price) {
                            price.textContent = effectiveUnitPrice(item);
                        }

                        if (line) {
                            line.textContent = item.is_generated_gift ? `FREE / ${item.line_subtotal}` : item.line_subtotal;
                        }

                        if (giftQuantity) {
                            giftQuantity.textContent = item.quantity || '1';
                        }

                        if (giftUnitPrice) {
                            giftUnitPrice.textContent = item.unit_price || '';
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
                            offer.classList.remove('ws-offer-coupon', 'ws-offer-auto', 'ws-offer-blue', 'cart-offer-panel', 'is-coupon', 'is-blue');
                            offer.classList.add(offerBoxClass(item.promotion));
                        }

                        if (offerIcon) {
                            offerIcon.className = 'd-flex align-items-center justify-content-center text-white rounded';
                            offerIcon.style.cssText = `width:24px;height:24px;background:${offerIconColor(item.promotion)};font-size:10px;`;
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
