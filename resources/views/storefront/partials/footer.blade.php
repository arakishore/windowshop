<footer class="tf-footer footer-s5 bg-dark">

    <div class="position-relative">

        <div class="fake-class bottom-0 bg-white_10 d-none d-sm-flex"></div>
        <div class="container-full">
            <div class="footer-inner flat-spacing">
                @php($footerContact = $storefrontContact ?? [])
                <div class="col-left">
                    <div class="footer-col-block type-white footer-wrap-start">
                        <a href="{{ route('storefront.home') }}" class="footer-logo-link d-inline-block mb-16" aria-label="{{ $marketplaceName }} home">
                            <img loading="lazy" width="150" height="30" src="{{ $storefrontFooterLogoUrl ?? asset('assets/admin/images/logov2.png') }}" alt="{{ $marketplaceName }}">
                        </a>
                        @if(($footerContact['phone_href'] ?? null) || ($footerContact['office_address'] ?? null) || ($footerContact['email'] ?? null) || ($footerContact['social_links'] ?? []))
                        <p class="footer-heading footer-heading-mobile text-white">OUR STORE</p>
                        <div class="tf-collapse-content">
                            @if(($footerContact['phone_href'] ?? null))
                                <a href="{{ $footerContact['phone_href'] }}" class="text-white link h4 fw-medium mb-12">{{ $footerContact['phone'] ?? $footerContact['phone_href'] }}</a>
                            @endif
                            @if(($footerContact['office_address'] ?? null))
                                <p class="cl-text-3 mb-4" style="white-space: pre-line;">{{ $footerContact['office_address'] }}</p>
                            @endif
                            @if(($footerContact['email'] ?? null))
                                <a href="mailto:{{ $footerContact['email'] }}" class="cl-text-3 link mb-12">{{ $footerContact['email'] }}</a>
                            @endif
                            @if(($footerContact['social_links'] ?? []))
                                <div class="tf-social-icon-2 style-2 mt-12">
                                    @foreach(['Facebook' => 'FacebookLogo', 'Instagram' => 'InstagramLogo', 'X / Twitter' => 'XLogo', 'YouTube' => 'YoutubeLogo', 'LinkedIn' => 'LinkedinLogo'] as $label => $icon)
                                        @if(isset($footerContact['social_links'][$label]))
                                            <a href="{{ $footerContact['social_links'][$label] }}" class="text-white" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}"><i class="icon icon-{{ $icon }}" aria-hidden="true"></i></a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                
                <div class="col-center">
                    <div class="footer-link-list">
                        <div class="footer-col-block type-white footer-wrap-2">
                            <p class="footer-heading footer-heading-mobile text-white">COMPANY</p>
                            <div class="tf-collapse-content">
                                <ul class="footer-menu-list">
                                    <li><a href="{{ route('storefront.about') }}" class="cl-text-3 link">About Us</a></li>
                                    <li><a href="{{ route('storefront.stores') }}" class="cl-text-3 link">Our Stores</a></li>
                                    <li><a href="{{ route('storefront.testimonials') }}" class="cl-text-3 link">Testimonials</a></li>
                                    <li><a href="{{ route('storefront.contact') }}" class="cl-text-3 link">Contact us</a></li>
                                    <li><a href="blog.html" class="cl-text-3 link">Latest New</a></li>
                                    <li><a href="{{ route('storefront.account') }}" class="cl-text-3 link">My Account</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="footer-col-block type-white footer-wrap-3">
                            <p class="footer-heading footer-heading-mobile text-white">CUSTOMER</p>
                            <div class="tf-collapse-content">
                                <ul class="footer-menu-list">
                                    <li><a href="{{ route('storefront.shipping') }}" class="cl-text-3 link">Shipping</a></li>
                                    <li><a href="{{ route('storefront.returns') }}" class="cl-text-3 link">Return &amp; Refund</a></li>
                                    <li><a href="{{ route('storefront.privacy') }}" class="cl-text-3 link">Privacy Policy</a></li>
                                    <li><a href="{{ route('storefront.terms') }}" class="cl-text-3 link">Terms &amp; Conditions</a></li>
                                    <li><a href="{{ route('storefront.faq') }}" class="cl-text-3 link">Orders FAQs</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="footer-col-block type-white footer-wrap-4">
                            <p class="footer-heading footer-heading-mobile text-white">MY ACCOUNT</p>
                            <div class="tf-collapse-content">
                                <ul class="footer-menu-list">
                                    <li><a href="{{ route('storefront.login') }}" class="cl-text-3 link">Login</a>
                                    </li>
                                    <li><a href="{{ route('storefront.register') }}" class="cl-text-3 link">Sign
                                            up</a></li>
                                    <li><a href="{{ route('storefront.account') }}" class="cl-text-3 link">My Account</a></li>
                                    <li><a href="{{ route('storefront.account.wishlist') }}" class="cl-text-3 link">Wish List</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                 
                <div class="col-right">
                    <div class="footer-col-block type-white footer-wrap-end">
                        <p class="footer-heading footer-heading-mobile text-white">NEWSLETTER</p>
                        <div class="tf-collapse-content">
                            <p class="footer-desc cl-text-3 mb-16">
                                Subscribe for store updates and discounts.
                            </p>
                            <form class="form-sub mb-16">
                                <fieldset>
                                    <input type="email" placeholder="Enter your e-mail" required="">
                                </fieldset>
                                <button type="submit" class="btn-action">
                                    <i class="icon icon-ArrowUpRight"></i>
                                </button>
                            </form>
                            <p class="text-remember cl-text-3">
                                By clicking subcribe, you agree to the
                                <a href="{{ route('storefront.terms') }}" class="text-white link link-underline">
                                    Terms of Service
                                </a>
                                and
                                <a href="{{ route('storefront.privacy') }}" class="text-white link link-underline">
                                    Privacy Policy
                                </a>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container-full">
            <div class="inner-bottom">
                <div class="tf-list list-currenci">


                </div>
                <p class="text-nocopy cl-text-3">
                    &copy; {{ now()->year }} {{ $marketplaceName }}. All Rights Reserved.
                </p>
                <ul class="tf-list payment-list">
                    <li><img loading="lazy" width="38" height="24"
                            src="{{ asset('assets/storefront/images/payment/visa.svg') }}" alt="Image"></li>
                    <li><img loading="lazy" width="38" height="24"
                            src="{{ asset('assets/storefront/images/payment/master-card.svg') }}" alt="Image"></li>
                    <li><img loading="lazy" width="38" height="24"
                            src="{{ asset('assets/storefront/images/payment/amex.svg') }}" alt="Image"></li>
                    <li><img loading="lazy" width="38" height="24"
                            src="{{ asset('assets/storefront/images/payment/paypal.svg') }}" alt="Image"></li>
                    <li><img loading="lazy" width="38" height="24"
                            src="{{ asset('assets/storefront/images/payment/water.svg') }}" alt="Image"></li>
                    <li><img loading="lazy" width="38" height="24"
                            src="{{ asset('assets/storefront/images/payment/paypal.svg') }}" alt="Image"></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
