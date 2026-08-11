@extends('storefront.layouts.app')

@section('title', 'Customer Register | WindowShop')
@section('meta_description', 'Static customer registration page for WindowShop shoppers.')

@push('styles')
    <style>
        .customer-register-wrap {
            background: #eef0f3;
        }

        .customer-register-shell {
            display: grid;
            grid-template-columns: minmax(320px, .9fr) minmax(360px, 1.1fr);
            gap: 0;
            max-width: 1120px;
            margin: 0 auto;
            padding: 22px;
            background: #ffffff;
        }

        .customer-register-form {
            padding: 44px 42px;
            background: #ffffff;
        }

        .customer-register-form h1 {
            margin-bottom: 4px;
            font-size: 24px;
            line-height: 1.25;
        }

        .customer-register-form .auth-subtitle {
            margin-bottom: 22px;
            color: #8a8a8a;
            font-size: 12px;
        }

        .customer-register-form .form-label {
            margin-bottom: 7px;
            color: #222222;
            font-size: 12px;
            font-weight: 500;
        }

        .customer-register-wrap .form-get input {
            border-color: rgba(18, 18, 18, .08);
            background: #ffffff;
            border-radius: 8px;
        }

        .customer-register-visual {
            position: relative;
            min-height: 600px;
            padding: 42px;
            overflow: hidden;
            color: #ffffff;
            background:
                linear-gradient(0deg, rgba(17, 17, 17, .76), rgba(17, 17, 17, .54)),
                url("{{ asset('assets/storefront/images/section/store-1.jpg') }}") center / cover;
        }

        .customer-register-visual h3 {
            max-width: 470px;
            margin-bottom: 16px;
            color: #ffffff;
            font-size: 34px;
            line-height: 1.15;
        }

        .customer-register-visual p {
            max-width: 480px;
            color: rgba(255, 255, 255, .78);
        }

        .customer-benefit-list {
            display: grid;
            gap: 14px;
            margin-top: 34px;
        }

        .customer-benefit-item {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(8px);
            transition: transform .24s ease, border-color .24s ease, background .24s ease, box-shadow .24s ease;
        }

        .customer-benefit-item:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, .36);
            background: rgba(255, 255, 255, .16);
            box-shadow: 0 18px 36px rgba(0, 0, 0, .18);
        }

        .customer-benefit-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #ffffff;
            color: #e14343;
            transition: transform .24s ease, background .24s ease, color .24s ease;
        }

        .customer-benefit-item:hover .customer-benefit-icon {
            transform: scale(1.08);
            background: #e14343;
            color: #ffffff;
        }

        .customer-benefit-item h6 {
            margin-bottom: 3px;
            color: #ffffff;
            font-size: 15px;
        }

        .customer-benefit-item span {
            
            font-size: 13px;
            line-height: 1.45;
        }

        .customer-register-actions {
            display: grid;
            gap: 12px;
            margin-top: 22px;
        }

        .customer-register-actions .tf-btn {
            width: 100%;
        }

        .customer-auth-link {
            color: #111111;
            font-weight: 600;
        }

        .customer-register-login {
            color: #777777;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 991px) {
            .customer-register-shell {
                grid-template-columns: 1fr;
                padding: 14px;
            }

            .customer-register-form,
            .customer-register-visual {
                padding: 32px 24px;
            }

            .customer-register-visual {
                min-height: auto;
            }
        }
    </style>
@endpush

@section('content')
    <section class="flat-spacing customer-register-wrap">
        <div class="container">
            <div class="customer-register-shell">
                <div class="customer-register-form">
                    <h1>Create Your Account</h1>
                    <p class="auth-subtitle">Welcome back! Please enter your details.</p>

                    <form action="#;" method="POST" class="form-get">
                        <h6 class="mb-16">Your Personal Details</h6>
                        <div class="mb-16">
                            <label for="customer_first_name" class="form-label">Name</label>
                            <fieldset>
                                <input id="customer_first_name" type="text">
                            </fieldset>
                        </div>
                        <div class="mb-16">
                            <label for="customer_last_name" class="form-label">Last Name</label>
                            <fieldset>
                                <input id="customer_last_name" type="text">
                            </fieldset>
                        </div>
                        <div class="mb-16">
                            <label for="customer_mobile" class="form-label">Phone Number</label>
                            <fieldset>
                                <input id="customer_mobile" type="tel">
                            </fieldset>
                        </div>
                        <div class="mb-16">
                            <label for="customer_register_email" class="form-label">E-Mail</label>
                            <fieldset>
                                <input id="customer_register_email" type="email">
                            </fieldset>
                        </div>

                        <h6 class="mb-16 mt-24">Your Password</h6>
                        <div class="mb-16">
                            <label for="customer_register_password" class="form-label">Password</label>
                            <fieldset>
                                <input id="customer_register_password" type="password">
                            </fieldset>
                        </div>
                        <div class="mb-16">
                            <label for="customer_confirm_password" class="form-label">Confirm password: <span class="text-primary">*</span></label>
                            <fieldset>
                                <input id="customer_confirm_password" type="password">
                            </fieldset>
                        </div>

                        <label class="checkbox-wrap mb-0">
                            <input type="checkbox">
                            <span class="checkbox-box"></span>
                            <span class="text-caption-01">I accept the terms and conditions.</span>
                        </label>

                        <div class="customer-register-actions">
                            <button type="submit" class="tf-btn animate-btn">Sign in</button>
                            <p class="customer-register-login mb-0">
                                Already have an account?
                                <a href="{{ route('storefront.login') }}" class="customer-auth-link">Sign in</a>
                            </p>
                        </div>
                    </form>
                </div>

                <div class="customer-register-visual">
                    <h3>Join WindowShop For Faster Local Shopping</h3>
                    <p>
                        Create an account once and keep your favourite nearby shops, order details, and customer
                        information ready whenever you return.
                    </p>

                    <div class="customer-benefit-list">
                        <div class="customer-benefit-item">
                            <span class="customer-benefit-icon"><i class="icon icon-Handbag"></i></span>
                            <div>
                                <h6>Faster Checkout</h6>
                                <span>Save your details so every future order takes less time.</span>
                            </div>
                        </div>
                        <div class="customer-benefit-item">
                            <span class="customer-benefit-icon"><i class="icon icon-storefront"></i></span>
                            <div>
                                <h6>Follow Local Shops</h6>
                                <span>Receive new offers, latest trends, and fresh arrivals from nearby stores.</span>
                            </div>
                        </div>
                        <div class="customer-benefit-item">
                            <span class="customer-benefit-icon"><i class="icon icon-Timer"></i></span>
                            <div>
                                <h6>Order Updates</h6>
                                <span>Stay ready for order status, pickup notes, and purchase history.</span>
                            </div>
                        </div>
                        <div class="customer-benefit-item">
                            <span class="customer-benefit-icon"><i class="icon icon-SealPercent"></i></span>
                            <div>
                                <h6>New Offers</h6>
                                <span>Receive fresh deals, seasonal sales, and limited-time shop discounts.</span>
                            </div>
                        </div>
                        <div class="customer-benefit-item">
                            <span class="customer-benefit-icon"><i class="icon icon-Star"></i></span>
                            <div>
                                <h6>Latest Trends</h6>
                                <span>Discover new arrivals and popular products from local merchants.</span>
                            </div>
                        </div>
                        <div class="customer-benefit-item">
                            <span class="customer-benefit-icon"><i class="icon icon-HeartStraight"></i></span>
                            <div>
                                <h6>Saved Favourites</h6>
                                <span>Keep favourite products and trusted shops easy to find again.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
