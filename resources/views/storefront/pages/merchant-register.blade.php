@extends('storefront.layouts.app')

@section('title', 'Sell on '.$marketplaceName)
@section('meta_description', 'Create a merchant account and start preparing your store on '.$marketplaceName.'.')

@push('styles')
    <style>
        .merchant-register-page { background: #f5f6f8; }
        .merchant-register-shell { display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(360px, .92fr); max-width: 1120px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(24, 32, 48, .1); }
        .merchant-register-form { padding: 42px 46px 44px; }
        .merchant-register-heading { display: flex; gap: 20px; align-items: flex-start; justify-content: space-between; margin-bottom: 26px; }
        .merchant-register-heading-copy { min-width: 0; }
        .merchant-register-heading h1 { margin-bottom: 9px; font-size: clamp(28px, 3vw, 38px); line-height: 1.16; }
        .merchant-register-eyebrow { margin-bottom: 8px; color: var(--primary); font-size: 12px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
        .merchant-register-intro { max-width: 570px; margin-bottom: 0; color: #6b7280; font-size: 15px; line-height: 1.6; }
        .merchant-register-login-top { flex: 0 0 auto; margin-top: 2px; color: #6b7280; font-size: 12px; line-height: 1.45; text-align: right; }
        .merchant-register-login-top a { display: block; margin-top: 2px; color: #111; font-weight: 700; }
        .merchant-register-form input, .merchant-register-form select { width: 100%; min-height: 46px; border: 1px solid #e1e4e8; border-radius: 8px; padding: 10px 13px; background: #fff; }
        .merchant-register-form .form-label { display: block; margin-bottom: 7px; font-size: 13px; font-weight: 600; }
        .merchant-register-aside { padding: 44px 42px; color: #fff; background: linear-gradient(145deg, #17243a 0%, #264a70 100%); }
        .merchant-register-aside h2 { max-width: 390px; margin-bottom: 12px; color: #fff; font-size: clamp(27px, 2.7vw, 36px); line-height: 1.18; }
        .merchant-register-aside-intro { max-width: 420px; margin-bottom: 28px; color: rgba(255, 255, 255, .76); font-size: 14px; line-height: 1.62; }
        .merchant-benefits { display: grid; gap: 13px; }
        .merchant-benefit { display: grid; grid-template-columns: 42px minmax(0, 1fr); gap: 13px; align-items: center; padding: 13px 14px; border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; background: rgba(255, 255, 255, .07); }
        .merchant-benefit-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; color: #fff; background: rgba(255, 255, 255, .13); font-size: 20px; }
        .merchant-benefit h3 { margin-bottom: 2px; color: #fff; font-size: 14px; font-weight: 700; }
        .merchant-benefit p { margin-bottom: 0; color: rgba(255, 255, 255, .7); font-size: 12px; line-height: 1.45; }
        .merchant-ready { margin-top: 28px; padding-top: 25px; border-top: 1px solid rgba(255, 255, 255, .18); }
        .merchant-ready-title { margin-bottom: 14px; color: rgba(255, 255, 255, .68); font-size: 11px; font-weight: 700; letter-spacing: .11em; }
        .merchant-ready-list { display: grid; gap: 9px; margin: 0; padding: 0; list-style: none; }
        .merchant-ready-list li { display: flex; gap: 9px; align-items: flex-start; color: rgba(255, 255, 255, .9); font-size: 13px; line-height: 1.4; }
        .merchant-ready-list i { margin-top: 1px; color: #8ce0ba; font-size: 16px; }
        .merchant-publication-note { display: flex; gap: 9px; margin-top: 19px; padding: 12px 13px; border-radius: 9px; color: rgba(255, 255, 255, .7); background: rgba(8, 18, 33, .24); font-size: 11px; line-height: 1.5; }
        .merchant-publication-note i { flex: 0 0 auto; margin-top: 2px; color: #b7d6f4; font-size: 15px; }
        .merchant-register-error { margin-top: 6px; color: #c62828; font-size: 12px; }
        @media (max-width: 991px) {
            .merchant-register-shell { grid-template-columns: minmax(0, 1fr) minmax(320px, .85fr); }
            .merchant-register-form, .merchant-register-aside { padding: 34px 30px; }
            .merchant-register-heading { display: block; }
            .merchant-register-login-top { margin-top: 12px; text-align: left; }
            .merchant-register-login-top a { display: inline; margin-left: 4px; }
        }
        @media (max-width: 767px) {
            .merchant-register-page { padding-top: 28px; padding-bottom: 28px; }
            .merchant-register-shell { grid-template-columns: 1fr; border-radius: 12px; }
            .merchant-register-form, .merchant-register-aside { padding: 28px 22px; }
            .merchant-register-heading h1 { font-size: 29px; }
            .merchant-register-aside h2 { font-size: 27px; }
            .merchant-benefit { padding: 11px 12px; }
            .merchant-benefit-icon { width: 38px; height: 38px; }
        }
    </style>
@endpush

@section('content')
    <section class="flat-spacing merchant-register-page">
        <div class="container">
            <div class="merchant-register-shell">
                <div class="merchant-register-form">
                    <div class="merchant-register-heading">
                        <div class="merchant-register-heading-copy">
                            <p class="merchant-register-eyebrow">Merchant registration</p>
                            <h1>Sell on {{ $marketplaceName }}</h1>
                            <p class="merchant-register-intro">Bring your local shop online. Create your account and start building your digital storefront in minutes.</p>
                        </div>
                        <p class="merchant-register-login-top">
                            Already a merchant?
                            <a href="{{ route('merchant.login') }}">Merchant Login</a>
                        </p>
                    </div>

                    <form method="POST" action="{{ route('storefront.merchant-register.store') }}">
                        @csrf

                        <h6 class="mb-16">Owner / account</h6>
                        <div class="mb-16">
                            <label class="form-label" for="merchant_owner_name">Owner or contact name</label>
                            <input id="merchant_owner_name" name="name" value="{{ old('name', $existingUser?->name) }}" required>
                            @error('name') <p class="merchant-register-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="mb-16">
                            <label class="form-label" for="merchant_email">Email</label>
                            <input id="merchant_email" name="email" type="email" value="{{ old('email', $existingUser?->email) }}" {{ $existingUser ? 'readonly' : '' }} required>
                            @error('email')
                                <p class="merchant-register-error">{{ $message }}</p>
                                @guest <p class="mt-2 mb-0"><a href="{{ route('storefront.login') }}" class="fw-semibold">Sign in to continue</a></p> @endguest
                            @enderror
                        </div>
                        <div class="mb-16">
                            <label class="form-label" for="merchant_mobile">Mobile</label>
                            <input id="merchant_mobile" name="mobile" type="tel" value="{{ old('mobile', $existingUser?->mobile) }}" maxlength="20" required>
                            @error('mobile') <p class="merchant-register-error">{{ $message }}</p> @enderror
                        </div>

                        @guest
                            <div class="row">
                                <div class="col-md-6 mb-16">
                                    <label class="form-label" for="merchant_password">Password</label>
                                    <input id="merchant_password" name="password" type="password" required>
                                    @error('password') <p class="merchant-register-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-md-6 mb-16">
                                    <label class="form-label" for="merchant_password_confirmation">Confirm password</label>
                                    <input id="merchant_password_confirmation" name="password_confirmation" type="password" required>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info">You are adding Merchant access to your existing account. Your password and Customer information will not be changed.</div>
                        @endguest

                        <h6 class="mt-24 mb-16">Business</h6>
                        <div class="mb-16">
                            <label class="form-label" for="merchant_business_name">Business name</label>
                            <input id="merchant_business_name" name="business_name" value="{{ old('business_name') }}" required>
                            @error('business_name') <p class="merchant-register-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="mb-20">
                            <label class="form-label" for="merchant_business_type">Business type</label>
                            <select id="merchant_business_type" name="business_type" required>
                                <option value="">Select business type</option>
                                @foreach ($businessTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('business_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('business_type') <p class="merchant-register-error">{{ $message }}</p> @enderror
                        </div>

                        <label class="d-flex gap-2 align-items-start mb-20">
                            <input class="mt-1" style="width:auto;min-height:auto" type="checkbox" name="terms" value="1" @checked(old('terms'))>
                            <span>I accept the <a href="{{ route('storefront.terms') }}" target="_blank" rel="noopener noreferrer">Terms &amp; Conditions</a> and acknowledge the <a href="{{ route('storefront.privacy') }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.</span>
                        </label>
                        @error('terms') <p class="merchant-register-error mb-16">{{ $message }}</p> @enderror

                        <button class="tf-btn animate-btn w-100" type="submit">Create Merchant Account</button>
                        <p class="text-center text-muted mt-16 mb-0">Already a merchant? <a class="fw-semibold" href="{{ route('merchant.login') }}">Merchant Login</a></p>
                    </form>
                </div>

                <aside class="merchant-register-aside">
                    <h2>Grow your local business with {{ $marketplaceName }}</h2>
                    <p class="merchant-register-aside-intro">Create your digital storefront and let nearby customers discover your products before visiting your shop.</p>

                    <div class="merchant-benefits">
                        <div class="merchant-benefit">
                            <span class="merchant-benefit-icon"><i class="icon icon-storefront" aria-hidden="true"></i></span>
                            <div><h3>Your Online Storefront</h3><p>Showcase your shop and products online.</p></div>
                        </div>
                        <div class="merchant-benefit">
                            <span class="merchant-benefit-icon"><i class="icon icon-MapPin" aria-hidden="true"></i></span>
                            <div><h3>Reach Local Customers</h3><p>Help nearby shoppers discover your products and store.</p></div>
                        </div>
                        <div class="merchant-benefit">
                            <span class="merchant-benefit-icon"><i class="icon icon-Handbag" aria-hidden="true"></i></span>
                            <div><h3>Sell Online &amp; In Store</h3><p>Manage your storefront and shop sales from one place.</p></div>
                        </div>
                        <div class="merchant-benefit">
                            <span class="merchant-benefit-icon"><i class="icon icon-SealPercent" aria-hidden="true"></i></span>
                            <div><h3>Offers &amp; Promotions</h3><p>Create offers and promotions to attract customers.</p></div>
                        </div>
                    </div>

                    <div class="merchant-ready">
                        <p class="merchant-ready-title">GET READY BEFORE GOING LIVE</p>
                        <ul class="merchant-ready-list">
                            <li><i class="icon icon-CheckCircle1" aria-hidden="true"></i><span>Create and configure your shop</span></li>
                            <li><i class="icon icon-CheckCircle1" aria-hidden="true"></i><span>Add products, photos, prices and stock</span></li>
                            <li><i class="icon icon-CheckCircle1" aria-hidden="true"></i><span>Set pickup and delivery options</span></li>
                            <li><i class="icon icon-CheckCircle1" aria-hidden="true"></i><span>Create offers and promotions</span></li>
                        </ul>
                        <p class="merchant-publication-note">
                            <i class="icon icon-ShieldCheck" aria-hidden="true"></i>
                            <span>Your store becomes publicly visible after merchant approval and the normal shop publication requirements are met.</span>
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
    @if(($merchantTestimonials ?? collect())->isNotEmpty())
        <section class="flat-spacing">
            <div class="container">
                <div class="sect-heading type-2 text-center wow fadeInUp">
                    <h3 class="s-title">
                        Merchant Say!
                    </h3>
                    <p class="s-desc text-body-1 cl-text-2">
                        Hear from shop owners growing their business with {{ $marketplaceName }}.
                    </p>
                </div>
                <div dir="ltr" class="swiper tf-swiper" data-preview="2" data-tablet="2" data-mobile-sm="1"
                    data-mobile="1" data-space-lg="30" data-space-md="15" data-space="10" data-pagination="1"
                    data-pagination-sm="2" data-pagination-md="2" data-pagination-lg="2">
                    <div class="swiper-wrapper">
                        @foreach ($merchantTestimonials as $testimonial)
                            <div class="swiper-slide">
                                <div class="testimonial-v01 style-2 wow fadeInUp">
                                    <div class="tes-content">
                                        <div class="tes_author">
                                            <h5 class="author-name">{{ $testimonial->name }}</h5>
                                        </div>
                                        <p class="tes_text h6 fw-medium">
                                            &ldquo;{{ $testimonial->body }}&rdquo;
                                        </p>
                                        <div class="tes_product">
                                            <div class="product-image">
                                                @if($testimonial->photo_path)
                                                    <img loading="lazy" width="60" height="60"
                                                        src="{{ asset('storage/'.$testimonial->photo_path) }}"
                                                        alt="{{ $testimonial->name }}">
                                                @else
                                                    <span class="testimonial-avatar-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($testimonial->name), 0, 1)) }}</span>
                                                @endif
                                            </div>
                                            <div class="product-infor">
                                                <span class="link fw-medium lh-24">{{ $testimonial->name }}</span>
                                                @if($testimonial->business_name)
                                                    <div class="fw-medium">{{ $testimonial->business_name }}</div>
                                                @endif
                                                @if($testimonial->designation || $testimonial->location)
                                                    <div class="text-caption-01 cl-text-3">{{ trim($testimonial->designation.($testimonial->designation && $testimonial->location ? ', ' : '').$testimonial->location) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="sw-line-default style-2 tf-sw-pagination"></div>
                </div>
            </div>
        </section>
    @endif
@endsection

@push('styles')
    <style>
        .testimonial-avatar-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #eef1f5;
            color: #5b6b82;
            font-size: 24px;
            font-weight: 700;
        }
        .tes_product .product-image {
            border-radius: 50%;
            overflow: hidden;
        }
    </style>
@endpush
