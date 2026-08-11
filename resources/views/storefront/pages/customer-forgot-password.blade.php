@extends('storefront.layouts.app')

@section('title', 'Forgot Password | WindowShop')
@section('meta_description', 'Static customer forgotten password page for WindowShop shoppers.')

@push('styles')
    <style>
        .customer-auth-wrap {
            background: #f7f7f7;
        }

        .customer-auth-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            gap: 28px;
            align-items: start;
            max-width: 920px;
            margin: 0 auto;
            padding: 24px;
            border: 1px solid #d8dee6;
            border-radius: 4px;
            background: #ffffff;
        }

        .customer-auth-panel h4 {
            margin-bottom: 10px;
            font-size: 24px;
        }

        .customer-auth-panel .form-label {
            margin-bottom: 8px;
            color: #222;
            font-size: 12px;
        }

        .customer-auth-wrap .form-get input {
            border-color: rgba(18, 18, 18, .08);
            background: #ffffff;
            border-radius: 8px;
        }

        .password-help-box {
            display: grid;
            gap: 12px;
            margin-top: 24px;
            padding: 18px;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .password-help-item {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
        }

        .password-help-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #ffffff;
            color: #e14343;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(18, 18, 18, .08);
        }

        .password-help-item h6 {
            margin-bottom: 2px;
            font-size: 14px;
        }

        .password-help-item p {
            margin-bottom: 0;
            color: #666666;
            font-size: 13px;
            line-height: 1.45;
        }

        .forgot-password-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100%;
            padding: 12px;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .forgot-password-visual img {
            width: 100%;
            max-width: 240px;
            max-height: 420px;
            object-fit: contain;
            border-radius: 8px;
        }

        .customer-auth-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-top: 24px;
        }

        .customer-auth-link {
            color: #0096d6;
            font-size: 13px;
        }

        @media (max-width: 575px) {
            .customer-auth-panel {
                grid-template-columns: 1fr;
            }

            .forgot-password-visual {
                order: -1;
                min-height: auto;
            }

            .forgot-password-visual img {
                width: 30%;
            }

            .customer-auth-actions {
                align-items: stretch;
                flex-direction: column;
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
                    <a href="{{ route('storefront.login') }}" class="text-caption-01 cl-text-3 link">Customer Login</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Forgotten Password</p>
                </div>
                <h3>Forgotten Password</h3>
            </div>
        </div>
    </section>

    <section class="flat-spacing customer-auth-wrap">
        <div class="container">
            <div class="customer-auth-panel">
                <div>
                    <h4>Forgotten Password</h4>
                    <p class="text-body-2 cl-text-2 mb-24">
                        Enter the e-mail address associated with your account. Password reset handling will be connected later.
                    </p>
                    <form action="#;" method="POST" class="form-get">
                        <label for="customer_forgot_email" class="form-label">E-Mail Address</label>
                        <fieldset>
                            <input id="customer_forgot_email" type="email">
                        </fieldset>
                        <div class="customer-auth-actions">
                            <a href="{{ route('storefront.login') }}" class="customer-auth-link">Back to Login</a>
                            <button type="submit" class="tf-btn animate-btn small">Continue</button>
                        </div>
                    </form>
                    <div class="password-help-box">
                        <div class="password-help-item">
                            <span class="password-help-step">1</span>
                            <div>
                                <h6>Enter your account e-mail</h6>
                                <p>Use the same e-mail address you used while creating your customer account.</p>
                            </div>
                        </div>
                        <div class="password-help-item">
                            <span class="password-help-step">2</span>
                            <div>
                                <h6>Check your inbox</h6>
                                <p>Once password reset is connected, we will send a secure reset link to that e-mail.</p>
                            </div>
                        </div>
                        <div class="password-help-item">
                            <span class="password-help-step">3</span>
                            <div>
                                <h6>Create a new password</h6>
                                <p>Open the reset link, choose a new password, and return to login.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="forgot-password-visual">
                    <img src="{{ asset('assets/storefront/images/section/forgot_password.png') }}" alt="Customer recovering account password">
                </div>
            </div>
        </div>
    </section>
@endsection
