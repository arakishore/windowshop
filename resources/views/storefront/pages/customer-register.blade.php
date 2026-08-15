@extends('storefront.layouts.app')

@section('title', 'Customer Register | WindowShop')
@section('meta_description', 'Static customer registration page for WindowShop shoppers.')

@php($checkoutMode = $checkoutMode ?? false)

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

        .customer-register-field-error {
            display: none;
            margin-top: 6px;
            color: #c62828;
        }

        .customer-register-field-error.is-visible {
            display: block;
        }

        .customer-register-wrap .form-get input.is-invalid {
            border-color: #c62828;
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
            @if ($checkoutMode)
                <div class="checkout-progress" aria-label="Checkout progress">
                    <span class="active">Account</span>
                    <span>Address</span>
                    <span>Shipping</span>
                    <span>Payment</span>
                </div>
            @endif

            <div class="customer-register-shell">
                <div class="customer-register-form">
                    <h1>Create Your Account</h1>
                    <p class="auth-subtitle">Welcome back! Please enter your details.</p>

                    <form action="{{ route('storefront.register.store') }}" method="POST" class="form-get" data-customer-register-form novalidate>
                        @csrf
                        <h6 class="mb-16">Your Personal Details</h6>
                        <div class="mb-16">
                            <label for="customer_first_name" class="form-label">First Name</label>
                            <fieldset>
                                <input id="customer_first_name" name="name" type="text" value="{{ old('name') }}" data-register-name>
                            </fieldset>
                            <p class="text-caption-01 customer-register-field-error" data-field-error="name"></p>
                            @error('name')
                                <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-16">
                            <label for="customer_last_name" class="form-label">Last Name</label>
                            <fieldset>
                                <input id="customer_last_name" name="last_name" type="text" value="{{ old('last_name') }}">
                            </fieldset>
                            @error('last_name')
                                <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-16">
                            <label for="customer_mobile" class="form-label">Phone Number</label>
                            <fieldset>
                                <input id="customer_mobile" name="mobile" type="tel" value="{{ old('mobile') }}" data-register-mobile>
                            </fieldset>
                            <p class="text-caption-01 customer-register-field-error" data-field-error="mobile"></p>
                            @error('mobile')
                                <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-16">
                            <label for="customer_register_email" class="form-label">E-Mail</label>
                            <fieldset>
                                <input id="customer_register_email" name="email" type="email" value="{{ old('email') }}" data-register-email>
                            </fieldset>
                            <p class="text-caption-01 customer-register-field-error" data-field-error="email"></p>
                            @error('email')
                                <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        </div>

                        <h6 class="mb-16 mt-24">Your Password</h6>
                        <div class="mb-16">
                            <label for="customer_register_password" class="form-label">Password</label>
                            <fieldset>
                                <input id="customer_register_password" name="password" type="password" data-register-password>
                            </fieldset>
                            <p class="text-caption-01 customer-register-field-error" data-field-error="password"></p>
                            @error('password')
                                <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-16">
                            <label for="customer_confirm_password" class="form-label">Confirm password: <span class="text-primary">*</span></label>
                            <fieldset>
                                <input id="customer_confirm_password" name="password_confirmation" type="password" data-register-password-confirmation>
                            </fieldset>
                            <p class="text-caption-01 customer-register-field-error" data-field-error="password_confirmation"></p>
                        </div>

                        <label class="checkbox-wrap mb-0">
                            <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }} data-register-terms>
                            <span class="checkbox-box"></span>
                            <span class="text-caption-01">
                                I accept the
                                <a href="{{ route('storefront.terms') }}" target="_blank" rel="noopener noreferrer" class="customer-auth-link">Terms &amp; Conditions</a>.
                            </span>
                        </label>
                        <p class="text-caption-01 customer-register-field-error" data-field-error="terms"></p>
                        @error('terms')
                            <p class="text-caption-01 text-danger mt-1 mb-0">{{ $message }}</p>
                        @enderror

                        <div class="customer-register-actions">
                            <button type="submit" class="tf-btn animate-btn">Create Account</button>
                            <p class="customer-register-login mb-0">
                                Already have an account?
                                <a href="{{ $checkoutMode ? route('storefront.login', ['from' => 'checkout']) : route('storefront.login') }}" class="customer-auth-link">Sign in</a>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-customer-register-form]');

            if (!form) {
                return;
            }

            const fields = {
                name: form.querySelector('[data-register-name]'),
                mobile: form.querySelector('[data-register-mobile]'),
                email: form.querySelector('[data-register-email]'),
                password: form.querySelector('[data-register-password]'),
                passwordConfirmation: form.querySelector('[data-register-password-confirmation]'),
                terms: form.querySelector('[data-register-terms]'),
            };
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const mobilePattern = /^[0-9+\-\s()]{7,20}$/;

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

                if (!fields.name.value.trim()) {
                    setError(fields.name, 'name', 'Please enter your name.');
                    firstInvalid = firstInvalid || fields.name;
                } else {
                    setError(fields.name, 'name');
                }

                if (fields.mobile.value.trim() && !mobilePattern.test(fields.mobile.value.trim())) {
                    setError(fields.mobile, 'mobile', 'Please enter a valid phone number.');
                    firstInvalid = firstInvalid || fields.mobile;
                } else {
                    setError(fields.mobile, 'mobile');
                }

                if (!fields.email.value.trim()) {
                    setError(fields.email, 'email', 'Please enter your email address.');
                    firstInvalid = firstInvalid || fields.email;
                } else if (!emailPattern.test(fields.email.value.trim())) {
                    setError(fields.email, 'email', 'Please enter a valid email address.');
                    firstInvalid = firstInvalid || fields.email;
                } else {
                    setError(fields.email, 'email');
                }

                if (!fields.password.value) {
                    setError(fields.password, 'password', 'Please enter a password.');
                    firstInvalid = firstInvalid || fields.password;
                } else if (fields.password.value.length < 8) {
                    setError(fields.password, 'password', 'Password must be at least 8 characters.');
                    firstInvalid = firstInvalid || fields.password;
                } else {
                    setError(fields.password, 'password');
                }

                if (!fields.passwordConfirmation.value) {
                    setError(fields.passwordConfirmation, 'password_confirmation', 'Please confirm your password.');
                    firstInvalid = firstInvalid || fields.passwordConfirmation;
                } else if (fields.password.value !== fields.passwordConfirmation.value) {
                    setError(fields.passwordConfirmation, 'password_confirmation', 'Passwords do not match.');
                    firstInvalid = firstInvalid || fields.passwordConfirmation;
                } else {
                    setError(fields.passwordConfirmation, 'password_confirmation');
                }

                if (!fields.terms.checked) {
                    setError(fields.terms, 'terms', 'Please accept the terms and conditions.');
                    firstInvalid = firstInvalid || fields.terms;
                } else {
                    setError(fields.terms, 'terms');
                }

                if (shouldFocus) {
                    firstInvalid?.focus();
                }

                return firstInvalid === null;
            };

            Object.values(fields).forEach((input) => {
                input?.addEventListener(input.type === 'checkbox' ? 'change' : 'input', () => validate(false));
            });

            form.addEventListener('submit', (event) => {
                if (!validate(true)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
