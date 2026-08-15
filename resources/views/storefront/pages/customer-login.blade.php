@extends('storefront.layouts.app')

@section('title', 'Customer Login | WindowShop')
@section('meta_description', 'Customer login page for WindowShop shoppers.')

@php($checkoutMode = $checkoutMode ?? false)

@push('styles')
    <style>
        .customer-auth-wrap {
            background: #f7f7f7;
        }

        .customer-auth-card {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            min-height: 312px;
            padding: 24px;
            border: 1px solid #d8dee6;
            border-radius: 4px;
            background: #ffffff;
        }

        .customer-auth-card form {
            display: flex;
            flex: 1;
            flex-direction: column;
        }

        .customer-auth-card h4 {
            margin-bottom: 10px;
            font-size: 24px;
            line-height: 1.25;
        }

        .customer-auth-card .auth-subtitle {
            margin-bottom: 24px;
            color: #222;
            font-size: 13px;
            font-weight: 600;
        }

        .customer-auth-card .form-label {
            margin-bottom: 8px;
            color: #222;
            font-size: 12px;
        }

        .customer-auth-wrap .form-get input {
            border-color: rgba(18, 18, 18, .08);
            background: #ffffff;
            border-radius: 8px;
        }

        .customer-auth-card .tf-btn {
            min-height: 34px;
            padding: 8px 14px;
        }

        .customer-auth-intro {
            padding: 18px;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .customer-auth-benefits {
            display: grid;
            gap: 10px;
            margin-top: 20px;
        }

        .customer-auth-benefit {
            display: grid;
            grid-template-columns: 30px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
            color: #414141;
            font-size: 14px;
        }

        .customer-auth-benefit span:first-child {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ffffff;
            color: #e14343;
            box-shadow: 0 8px 20px rgba(18, 18, 18, .08);
        }

        .customer-auth-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: auto;
            padding-top: 22px;
        }

        .customer-auth-link {
            color: #0096d6;
            font-size: 12px;
        }

        .customer-auth-field-error {
            display: none;
            margin-top: 6px;
            color: #c62828;
        }

        .customer-auth-field-error.is-visible {
            display: block;
        }

        .customer-auth-wrap .form-get input.is-invalid {
            border-color: #c62828;
        }

        .checkout-progress {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
        }

        .checkout-progress span {
            padding: 6px 12px;
            border: 1px solid #d8dee6;
            border-radius: 999px;
            background: #ffffff;
        }

        .checkout-progress .active {
            color: #ffffff;
            border-color: #121212;
            background: #121212;
        }

        @media (max-width: 991px) {
            .customer-auth-returning {
                order: -1;
            }
        }
    </style>
@endpush

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">{{ $checkoutMode ? 'Checkout Account' : 'Customer Login' }}</p>
                </div>
                <h3>{{ $checkoutMode ? 'Checkout Account' : 'Customer Login' }}</h3>
            </div>
        </div>
    </section>

    <section class="flat-spacing customer-auth-wrap">
        <div class="container">
            @if ($checkoutMode)
                <div class="checkout-progress" aria-label="Checkout progress">
                    <span class="active">Account</span>
                    <span>Address</span>
                    <span>Shipping</span>
                    <span>Payment</span>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-6 d-flex customer-auth-new">
                    <div class="customer-auth-card">
                        <div class="customer-auth-intro">
                            <h4>New Customer</h4>
                            <p class="auth-subtitle mb-12">Register Account</p>
                            <p class="text-body-2 cl-text-2 mb-0">
                                Create an account to shop faster, follow local stores, and keep your order details ready
                                whenever you come back.
                            </p>
                        </div>
                        <div class="customer-auth-benefits">
                            <div class="customer-auth-benefit">
                                <span><i class="icon icon-Handbag"></i></span>
                                <span>Checkout faster with saved customer details.</span>
                            </div>
                            <div class="customer-auth-benefit">
                                <span><i class="icon icon-SealPercent"></i></span>
                                <span>Receive new offers and latest trends from nearby shops.</span>
                            </div>
                            <div class="customer-auth-benefit">
                                <span><i class="icon icon-Timer"></i></span>
                                <span>Track order updates and previous purchases in one place.</span>
                            </div>
                        </div>
                        <div class="customer-auth-actions">
                            <a href="{{ $checkoutMode ? route('storefront.register', ['from' => 'checkout']) : route('storefront.register') }}" class="tf-btn animate-btn small">Create Account</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-flex customer-auth-returning">
                    <div class="customer-auth-card">
                        <h4>Returning Customer</h4>
                        <p class="auth-subtitle">I am a returning customer</p>
                        <form action="{{ route('storefront.login.store') }}" method="POST" class="form-get" data-customer-login-form novalidate>
                            @csrf
                            <div class="mb-20">
                                <label for="customer_login_email" class="form-label">E-Mail Address</label>
                                <fieldset>
                                    <input id="customer_login_email" name="email" type="email" value="{{ old('email') }}" data-login-email>
                                </fieldset>
                                <p class="text-caption-01 customer-auth-field-error" data-field-error="email"></p>
                                @error('email')
                                    <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="mb-8">
                                <label for="customer_login_password" class="form-label">Password</label>
                                <fieldset>
                                    <input id="customer_login_password" name="password" type="password" data-login-password>
                                </fieldset>
                                <p class="text-caption-01 customer-auth-field-error" data-field-error="password"></p>
                                @error('password')
                                    <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>
                            <a href="{{ route('storefront.forgot-password') }}" class="customer-auth-link">Forgotten Password</a>
                            <div class="customer-auth-actions">
                                <button type="submit" class="tf-btn animate-btn small">Login & Continue</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-customer-login-form]');

            if (!form) {
                return;
            }

            const email = form.querySelector('[data-login-email]');
            const password = form.querySelector('[data-login-password]');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            const setError = (input, field, message = '') => {
                const error = form.querySelector(`[data-field-error="${field}"]`);

                input?.classList.toggle('is-invalid', message !== '');
                if (error) {
                    error.textContent = message;
                    error.classList.toggle('is-visible', message !== '');
                }
            };

            const validate = (shouldFocus = false) => {
                let firstInvalid = null;

                if (!email.value.trim()) {
                    setError(email, 'email', 'Please enter your email address.');
                    firstInvalid = firstInvalid || email;
                } else if (!emailPattern.test(email.value.trim())) {
                    setError(email, 'email', 'Please enter a valid email address.');
                    firstInvalid = firstInvalid || email;
                } else {
                    setError(email, 'email');
                }

                if (!password.value) {
                    setError(password, 'password', 'Please enter your password.');
                    firstInvalid = firstInvalid || password;
                } else {
                    setError(password, 'password');
                }

                if (shouldFocus) {
                    firstInvalid?.focus();
                }

                return firstInvalid === null;
            };

            [email, password].forEach((input) => {
                input?.addEventListener('input', () => validate(false));
            });

            form.addEventListener('submit', (event) => {
                if (!validate(true)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
