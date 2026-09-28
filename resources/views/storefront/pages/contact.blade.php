@extends('storefront.layouts.app')

@section('title', 'Contact Us | ' . $marketplaceName)
@section('meta_description', 'Contact '.$marketplaceName.' for customer support and marketplace questions.')

@push('styles')
<style>
    .contact-page { background: #f6f6f6; color: #121212; }
    .contact-page__hero { padding: 34px 0 30px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(180deg, #f8fafc 0%, #fff 100%); }
    .contact-page__breadcrumbs { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
    .contact-page__eyebrow { margin-bottom: 8px; color: #fd8301; font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .contact-page__title { margin: 0 0 12px; color: #111827; font-size: clamp(32px, 5vw, 54px); font-weight: 800; line-height: 1; }
    .contact-page__intro { max-width: 620px; margin: 0; color: #4b5563; font-size: 16px; line-height: 1.6; }
    .contact-page__content { padding: 30px 0 72px; }
    .contact-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .contact-card { display: flex; flex-direction: column; min-height: 360px; padding: 28px; border: 1px solid #ececec; border-radius: 10px; background: #fff; box-shadow: 0 6px 24px rgba(15, 23, 42, .08); transition: transform .2s ease, box-shadow .2s ease; }
    .contact-card:hover { transform: translateY(-3px); box-shadow: 0 12px 34px rgba(15, 23, 42, .13); }
    .contact-card__icon, .contact-info__icon { display: inline-flex; align-items: center; justify-content: center; width: 58px; height: 58px; flex: 0 0 58px; border-radius: 50%; background: #f4f4f4; color: #101010; font-size: 27px; }
    .contact-card h2 { margin: 18px 0 8px; font-size: 22px; line-height: 1.25; }
    .contact-card__copy { margin: 0 0 20px; color: #667085; font-size: 16px; line-height: 1.5; }
    .contact-card__address { display: flex; align-items: center; gap: 10px; min-height: 48px; margin: auto 0 10px; padding: 11px 13px; border-radius: 7px; background: #f5f5f5; color: #171717; overflow-wrap: anywhere; }
    .contact-card__cta { display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 52px; margin-top: auto; padding: 12px 16px; border-radius: 7px; background: #111; color: #fff; font-weight: 600; }
    .contact-card__cta:hover { background: #2b2b2b; color: #fff; }
    .contact-card__cta-label { display: inline-flex; align-items: center; gap: 10px; }
    .contact-card__unavailable { margin-top: auto; padding: 13px 15px; border-radius: 7px; background: #f5f5f5; color: #667085; }
    .contact-card__note { display: flex; align-items: flex-start; gap: 8px; margin: 16px 0 0; color: #667085; font-size: 13px; line-height: 1.45; }
    .contact-card__note .icon { margin-top: 2px; }
    .contact-socials { display: flex; flex-wrap: wrap; gap: 11px; margin-top: auto; }
    .contact-socials a { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 1px solid #ececec; border-radius: 50%; background: #fff; color: #171717; box-shadow: 0 6px 16px rgba(18, 18, 18, .07); font-size: 20px; transition: transform .2s ease, color .2s ease; }
    .contact-socials a:hover { transform: translateY(-2px); color: #ff6f00; }
    .contact-info { display: grid; grid-template-columns: 1fr 1fr; margin-top: 28px; padding: 25px 28px; border: 1px solid #ececec; border-radius: 10px; background: #fff; box-shadow: 0 12px 34px rgba(18, 18, 18, .05); }
    .contact-info__item { display: flex; gap: 20px; min-width: 0; }
    .contact-info__item + .contact-info__item { margin-left: 30px; padding-left: 30px; border-left: 1px solid #e7e7e7; }
    .contact-info h2 { margin: 2px 0 6px; font-size: 20px; }
    .contact-info p { margin: 0; color: #667085; line-height: 1.55; white-space: pre-line; }
    .contact-info__missing { font-style: italic; }
    @media (max-width: 1199px) { .contact-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) {
        .contact-page__hero { padding: 24px 0; }
        .contact-page__content { padding-top: 22px; }
        .contact-grid, .contact-info { grid-template-columns: 1fr; }
        .contact-card { min-height: 0; padding: 22px; }
        .contact-info { padding: 22px; }
        .contact-info__item + .contact-info__item { margin: 24px 0 0; padding: 24px 0 0; border-top: 1px solid #e7e7e7; border-left: 0; }
    }
</style>
@endpush

@section('content')
<div class="contact-page">
    <section class="contact-page__hero">
        <div class="container">
            <div class="contact-page__breadcrumbs"><a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a><i class="icon icon-CaretRightThin cl-text-3" aria-hidden="true"></i><span class="text-caption-01">Contact Us</span></div>
            <p class="contact-page__eyebrow">Contact Us</p>
            <h1 class="contact-page__title">How can we help?</h1>
            <p class="contact-page__intro">Need help with an order, finding a local shop, or any other question about {{ $marketplaceName }}? Choose the easiest way to reach us.</p>
        </div>
    </section>

    <section class="contact-page__content">
        <div class="container">
            <div class="contact-grid">
                <article class="contact-card">
                    <span class="contact-card__icon"><i class="icon icon-WhatsappLogo" aria-hidden="true"></i></span><h2>Chat on WhatsApp</h2>
                    <p class="contact-card__copy">Get quick help from our support team. We usually respond fast.</p>
                    @if($contactDetails['whatsapp_url'])
                        <a class="contact-card__cta" href="{{ $contactDetails['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"><span class="contact-card__cta-label"><i class="icon icon-WhatsappLogo" aria-hidden="true"></i>Chat on WhatsApp</span><i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
                    @else
                        <p class="contact-card__unavailable">WhatsApp support is not currently available.</p>
                    @endif
                    <p class="contact-card__note"><i class="icon icon-Timer" aria-hidden="true"></i>{{ $contactDetails['whatsapp_hours'] ?: $contactDetails['support_hours'] ?: 'Availability hours are not currently listed.' }}</p>
                </article>

                <article class="contact-card">
                    <span class="contact-card__icon"><i class="icon icon-Phone" aria-hidden="true"></i></span><h2>Call Us</h2>
                    <p class="contact-card__copy">Speak with our team for immediate assistance.</p>
                    @if($contactDetails['phone_href'])
                        <a class="contact-card__cta" href="{{ $contactDetails['phone_href'] }}"><span class="contact-card__cta-label"><i class="icon icon-Phone" aria-hidden="true"></i>Call Now</span><i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
                    @else
                        <p class="contact-card__unavailable">Telephone support is not currently available.</p>
                    @endif
                    <p class="contact-card__note"><i class="icon icon-Timer" aria-hidden="true"></i>{{ $contactDetails['support_hours'] ?: 'Support hours are not currently listed.' }}</p>
                </article>

                <article class="contact-card">
                    <span class="contact-card__icon"><i class="icon icon-Envelope" aria-hidden="true"></i></span><h2>Email Us</h2>
                    <p class="contact-card__copy">For detailed questions or non-urgent enquiries.</p>
                    @if($contactDetails['email'])
                        <div class="contact-card__address"><i class="icon icon-Envelope" aria-hidden="true"></i><span>{{ $contactDetails['email'] }}</span></div>
                        <a class="contact-card__cta" href="mailto:{{ $contactDetails['email'] }}"><span class="contact-card__cta-label"><i class="icon icon-Envelope" aria-hidden="true"></i>Send Email</span><i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
                    @else
                        <p class="contact-card__unavailable">Email support is not currently available.</p>
                    @endif
                    <p class="contact-card__note"><i class="icon icon-Timer" aria-hidden="true"></i>We aim to respond as soon as possible.</p>
                </article>

                <article class="contact-card">
                    <span class="contact-card__icon"><i class="icon icon-ShareNetwork" aria-hidden="true"></i></span><h2>Follow Our Updates</h2>
                    <p class="contact-card__copy">Discover local shops, new products, offers and {{ $marketplaceName }} updates.</p>
                    @if($contactDetails['social_links'])
                        <div class="contact-socials">
                            @foreach(['Facebook' => 'FacebookLogo', 'Instagram' => 'InstagramLogo', 'X / Twitter' => 'XLogo', 'YouTube' => 'YoutubeLogo', 'LinkedIn' => 'LinkedinLogo'] as $label => $icon)
                                @if(isset($contactDetails['social_links'][$label]))<a href="{{ $contactDetails['social_links'][$label] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}"><i class="icon icon-{{ $icon }}" aria-hidden="true"></i></a>@endif
                            @endforeach
                        </div>
                    @else
                        <p class="contact-card__unavailable">Social links are not currently available.</p>
                    @endif
                </article>
            </div>

            <section class="contact-info" aria-label="Office and support information">
                <div class="contact-info__item"><span class="contact-info__icon"><i class="icon icon-MapPin" aria-hidden="true"></i></span><div><h2>Our Office</h2><p class="{{ $contactDetails['office_address'] ? '' : 'contact-info__missing' }}">{{ $contactDetails['office_address'] ?: 'Office address is not currently listed.' }}</p></div></div>
                <div class="contact-info__item"><span class="contact-info__icon"><i class="icon icon-Timer" aria-hidden="true"></i></span><div><h2>Support Hours</h2><p class="{{ $contactDetails['support_hours'] ? '' : 'contact-info__missing' }}">{{ $contactDetails['support_hours'] ?: 'Support hours are not currently listed.' }}</p></div></div>
            </section>
        </div>
    </section>
</div>
@endsection
