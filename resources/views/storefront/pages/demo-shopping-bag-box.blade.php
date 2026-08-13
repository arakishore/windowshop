@extends('storefront.layouts.app')

@section('title', 'Shopping Bag Box Demo | WindowShop')
@section('meta_description', 'Demo page for testing a shopping bag shaped content box.')

@push('styles')
    <style>
        .bag-demo-wrap {
            min-height: 640px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 56px 16px;
            background: #f4f4f4;
        }

        .shopping-bag-box {
            position: relative;
            width: min(390px, 88vw);
            aspect-ratio: 1 / 1.05;
            margin-top: 58px;
            border-radius: 36px;
            background:
                linear-gradient(135deg, #083a6b 0 38%, transparent 38%),
                linear-gradient(135deg, transparent 0 57%, #ff8a00 57% 100%),
                #ffffff;
            box-shadow: 0 26px 58px rgba(8, 28, 52, .22);
            overflow: visible;
        }

        .shopping-bag-box::before {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -18px;
            width: 78%;
            height: 26px;
            transform: translateX(-50%);
            border-radius: 50%;
            background: rgba(0, 0, 0, .16);
            filter: blur(14px);
            z-index: -1;
        }

        .bag-handle {
            position: absolute;
            left: 50%;
            top: -74px;
            width: 190px;
            height: 138px;
            transform: translateX(-50%);
            border: 24px solid #062f5d;
            border-bottom: 0;
            border-radius: 120px 120px 0 0;
            z-index: 1;
        }

        .bag-ring {
            position: absolute;
            top: 22px;
            width: 38px;
            height: 38px;
            border: 9px solid #ffffff;
            border-radius: 50%;
            background: #062f5d;
            box-shadow: 0 8px 18px rgba(0, 0, 0, .18);
            z-index: 2;
        }

        .bag-ring.left {
            left: 62px;
        }

        .bag-ring.right {
            right: 62px;
        }

        .bag-product-photo {
            position: absolute;
            left: 50%;
            top: 56%;
            width: 82%;
            height: 80%;
            transform: translate(-50%, -50%);
            background: #ffffff;
            padding: 8px;
            box-shadow: 0 18px 34px rgba(8, 28, 52, .2);
            z-index: 4;
            overflow: hidden;
        }

        .bag-product-photo img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }

        .bag-shine {
            position: absolute;
            right: 48px;
            top: 180px;
            width: 70px;
            height: 22px;
            transform: rotate(-34deg);
            border-radius: 999px;
            background: rgba(255, 255, 255, .82);
            filter: blur(.2px);
        }

        @media (max-width: 575px) {
            .bag-demo-wrap {
                min-height: 560px;
                padding-top: 44px;
            }

            .shopping-bag-box {
                margin-top: 48px;
                border-radius: 28px;
            }

            .bag-handle {
                top: -58px;
                width: 150px;
                height: 108px;
                border-width: 18px;
            }

            .bag-ring {
                width: 34px;
                height: 34px;
                border-width: 8px;
            }

            .bag-ring.left {
                left: 44px;
            }

            .bag-ring.right {
                right: 44px;
            }

            .bag-product-photo {
                width: 82%;
                height: 78%;
                padding: 6px;
            }
        }
    </style>
@endpush

@section('content')
    <section class="bag-demo-wrap">
        <div class="shopping-bag-box" aria-label="Shopping bag shaped box demo">
            <div class="bag-handle"></div>
            <span class="bag-ring left"></span>
            <span class="bag-ring right"></span>
            <span class="bag-shine"></span>
            <div class="bag-product-photo">
                <img src="{{ asset('assets/storefront/images/product/product-2.jpg') }}" alt="Shopping bag product demo">
            </div>
        </div>
    </section>
@endsection
