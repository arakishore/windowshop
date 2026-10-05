@extends('storefront.layouts.app')

@section('title', 'FAQs | ' . $marketplaceName)
@section('meta_description', 'Frequently asked questions about '.$marketplaceName.', local shops, orders, shipping, returns, and support.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">FAQs</p>
                </div>
                <h3>FAQs</h3>
                <p class="cl-text-2">
                    Got questions? Find quick answers about local shops, product discovery, orders, shipping, and returns.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="row">
                <div class="col-lg-9">
                    @if(($faqGroups ?? collect())->isNotEmpty())
                        <ul class="faq-list">
                            @foreach($faqGroups as $group)
                                <li class="faq-item" id="{{ $group['key'] }}">
                                    <h4 class="faq_title">{{ $group['label'] }}</h4>
                                    <div class="faq_wrap" id="{{ $group['key'] }}-faq">
                                        @foreach($group['faqs'] as $faqIndex => $faq)
                                            @php
                                                $panelId = 'faq-'.$group['key'].'-'.$faq->getKey();
                                                $isOpen = $loop->parent->first && $faqIndex === 0;
                                            @endphp
                                            <div class="accordion-faq">
                                                <button type="button" class="accordion-title{{ $isOpen ? '' : ' collapsed' }}" data-bs-target="#{{ $panelId }}"
                                                    data-bs-toggle="collapse" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="{{ $panelId }}">
                                                    <span class="text h6">{{ $faq->question }}</span>
                                                    <span class="icon" aria-hidden="true"><span class="ic-accordion-custom"></span></span>
                                                </button>
                                                <div id="{{ $panelId }}" class="collapse{{ $isOpen ? ' show' : '' }}" data-bs-parent="#{{ $group['key'] }}-faq">
                                                    <div class="accordion-body">
                                                        <p class="cl-text-2">{!! nl2br(e($faq->answer)) !!}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="cl-text-2">No FAQs are available at the moment. Please contact us if you need help.</p>
                    @endif
                </div>
                <div class="col-lg-3">
                    <div class="faq-sidebar">
                        <h5 class="mb-16">Need More Help?</h5>
                        <p class="cl-text-2 mb-20">Send us your question and we will help you find the right information.</p>
                        <a href="{{ route('storefront.contact') }}" class="tf-btn animate-btn w-100">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <style>
        .accordion-faq .accordion-title {
            background: none;
            border: 0;
            padding: 0;
            width: 100%;
            font-family: inherit;
            font-size: inherit;
            color: inherit;
            text-align: left;
            cursor: pointer;
        }
    </style>
@endpush
