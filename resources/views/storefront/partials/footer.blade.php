<footer class="tf-footer footer-s5 bg-dark">

    <div class="position-relative">

        <div class="fake-class bottom-0 bg-white_10 d-none d-sm-flex"></div>
        <div class="container-full">
            <div class="footer-inner flat-spacing">
                <div class="col-left">
                    <div class="footer-col-block type-white footer-wrap-start">
                        <p class="footer-heading footer-heading-mobile text-white">OUR STORE</p>
                        <div class="tf-collapse-content">
                            <p class="cl-text-3 mb-4">
                                24/7 Support Center:
                            </p>
                            <a href="tel:0112348888" class="text-white link h4 fw-medium mb-12">
                                (+01) 1234 8888
                            </a>
                            <a href="ml.html?q=600+N+Michigan+Ave+Chicago,+IL+60611+USA" target="_blank"
                                class="cl-text-3 link mb-4">
                                600 N Michigan Ave, Chicago, IL 60611, USA
                            </a>
                            <a href="mailto:hi.amere@gmail.com" class="cl-text-3 link">
                                hi.amere@gmail.com
                            </a>
                        </div>
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
                    ©2026 Amerce. All Rights Reserved.
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
