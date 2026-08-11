@extends('storefront.layouts.app')

@section('title', 'Contact Us | WindowShop')
@section('meta_description', 'Contact WindowShop for support, merchant enquiries, local shop onboarding, and customer questions.')

@push('styles')
    <style>
        .contact-page-wrap {
            background: #f4f4f4;
        }

        .contact-panel {
            min-height: 100%;
            padding: 42px 40px;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 18px 60px rgba(18, 18, 18, .08);
        }

        .contact-panel.form-panel {
            background: #f0f0f0;
        }

        .contact-eyebrow {
            color: #e14343;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .contact-divider {
            height: 1px;
            background: rgba(18, 18, 18, .1);
            margin: 30px 0 24px;
        }

        .contact-social-list {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .contact-social-list a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #ffffff;
            color: #0c1730;
            box-shadow: 0 12px 28px rgba(18, 18, 18, .08);
            transition: transform .22s ease, color .22s ease, box-shadow .22s ease;
        }

        .contact-social-list a:hover {
            color: #e14343;
            transform: translateY(-3px);
            box-shadow: 0 16px 34px rgba(18, 18, 18, .13);
        }

        .contact-page-wrap .form-get input,
        .contact-page-wrap .form-get textarea {
            border-color: rgba(18, 18, 18, .08);
            background: #ffffff;
            border-radius: 8px;
        }

        .contact-page-wrap .form-get textarea {
            min-height: 138px;
        }

        @media (max-width: 767px) {
            .contact-panel {
                padding: 30px 22px;
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
                    <p class="text-caption-01">Contact Us</p>
                </div>
                <h3>Contact Us</h3>
                <p class="text-body-1 cl-text-2">Get in touch for support, merchant onboarding, shop page questions, or local discovery help.</p>
            </div>
        </div>
    </section>

    <section class="section-contact flat-spacing contact-page-wrap">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-5">
                    <div class="contact-panel">
                        <p class="contact-eyebrow text-caption-01 fw-semibold mb-16">Information About Us</p>
                        <h4 class="mb-16">Local shop help, handled with care.</h4>
                        <p class="text-body-1 cl-text-2 mb-0">
                            Whether you are a merchant setting up your store page or a customer trying to find the right
                            local shop, send us the details and we will guide you with practical options.
                        </p>

                        <div class="contact-divider"></div>

                        <h5 class="mb-20">Follow Our Updates</h5>
                        <ul class="contact-social-list">
                            <li><a href="#;" aria-label="Facebook"><i class="icon icon-FacebookLogo"></i></a></li>
                            <li><a href="#;" aria-label="Instagram"><i class="icon icon-InstagramLogo"></i></a></li>
                            <li><a href="#;" aria-label="X"><i class="icon icon-XLogo"></i></a></li>
                            <li><a href="#;" aria-label="YouTube"><i class="icon icon-YoutubeLogo"></i></a></li>
                            <li><a href="#;" aria-label="LinkedIn"><i class="icon icon-LinkedinLogo"></i></a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="contact-panel form-panel">
                        <h4 class="mb-12">Keep In Touch</h4>
                        <p class="mb-30 cl-text-2">
                            Tell us what kind of help you need and our team will help shape the next steps.
                        </p>
                        <form class="form-get" action="#;">
                            <div class="form-content">
                                <div class="tf-grid-layout sm-col-2">
                                    <fieldset>
                                        <input type="text" id="contact-name" placeholder="Name *" required>
                                    </fieldset>
                                    <fieldset>
                                        <input type="email" id="contact-email" placeholder="Email *" required>
                                    </fieldset>
                                    <fieldset>
                                        <input type="tel" id="contact-phone" placeholder="Phone *" required>
                                    </fieldset>
                                    <fieldset>
                                        <input type="text" id="contact-subject" placeholder="Subject *" required>
                                    </fieldset>
                                </div>
                                <fieldset>
                                    <textarea id="contact-message" placeholder="Message *" required></textarea>
                                </fieldset>
                            </div>
                            <button type="submit" class="tf-btn animate-btn">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
