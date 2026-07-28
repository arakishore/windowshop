{{-- Purpose: Merchant tax behaviour settings; legal tax data remains on merchant profile/address. --}}
@extends('layouts.merchant')

@section('title', 'Tax Settings | WindowShop')

@section('page_title', 'Tax Settings')

@push('styles')
    <style>
        .tax-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .tax-settings-section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray-600, #6c757d);
            border-bottom: 1px solid var(--border-color, #ddd);
            padding-bottom: .45rem;
            margin-bottom: .85rem;
        }

        .tax-help-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            margin-left: .25rem;
            color: var(--gray-600, #6c757d);
        }

        @media (max-width: 767.98px) {
            .tax-settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $value = fn (string $key, mixed $default = null) => old($key, $setting->{$key} ?? $default);
        $taxEnabled = (bool) $value('tax_enabled', false);
        $selectedTaxClass = $value('default_tax_class_id');
        $businessAddress = $merchant->businessAddress;
        $country = $businessAddress?->country;
        $state = $businessAddress?->state;
        $taxSystemLabel = $country?->iso2 === 'IN' ? 'GST' : 'Derived from business country';
        $gstNumber = trim((string) ($merchant->gst_number ?? ''));
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-0">Tax Settings</h5>
                <div class="text-muted fs-sm mt-1">Choose how WindowShop should behave for tax. Legal details come from Merchant Details.</div>
            </div>
            <span class="badge bg-secondary bg-opacity-10 text-secondary">Configuration only</span>
        </div>

        <form method="POST" action="{{ route('merchant.tax-settings.update') }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="alert alert-light border">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold mb-1">Business tax profile</div>
                            <div class="text-muted fs-sm">
                                Country: {{ $country?->name ?? 'Not set' }} |
                                State: {{ $state?->name ?? 'Not set' }} |
                                Tax system: {{ $taxSystemLabel }} |
                                GSTIN: {{ $gstNumber !== '' ? $gstNumber : 'Not set' }}
                            </div>
                        </div>
                        <a href="{{ route('merchant.details.edit') }}" class="btn btn-light btn-sm">
                            <i class="ph-pencil-simple me-1"></i>
                            Edit Merchant Details
                        </a>
                    </div>
                </div>

                <div class="alert alert-warning js-tax-enabled-warning {{ $taxEnabled ? '' : 'd-none' }}">
                    <div class="fw-semibold mb-1">Tax calculation is enabled.</div>
                    <div class="fs-sm">Make sure products are assigned a tax class before selling. Products and categories will get tax assignment controls in later tax steps.</div>
                </div>

                <div class="tax-settings-section-title">Tax Behaviour</div>
                <div class="tax-settings-grid">
                    <div>
                        <label class="form-label fw-semibold" for="tax_enabled">
                            Enable Tax Calculation
                            <i class="ph-question tax-help-icon" data-bs-popup="tooltip" title="Turn on when this merchant wants WindowShop to use tax behaviour in later product and POS steps."></i>
                        </label>
                        <select name="tax_enabled" id="tax_enabled" class="form-select @error('tax_enabled') is-invalid @enderror">
                            <option value="0" @selected(! $taxEnabled)>No</option>
                            <option value="1" @selected($taxEnabled)>Yes</option>
                        </select>
                        @error('tax_enabled')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="text-muted fs-sm mt-1">Example: choose No to keep tax calculation disabled even if GSTIN is saved in Merchant Details.</div>
                    </div>

                    <div>
                        <label class="form-label fw-semibold" for="default_tax_class_id">
                            Default Tax Class
                            <i class="ph-question tax-help-icon" data-bs-popup="tooltip" title="Required when tax is enabled. Product/category tax assignment comes in later steps."></i>
                        </label>
                        <select name="default_tax_class_id" id="default_tax_class_id" class="form-select @error('default_tax_class_id') is-invalid @enderror">
                            <option value="">No default</option>
                            @foreach ($taxClasses as $taxClass)
                                <option value="{{ $taxClass->id }}" @selected((string) $selectedTaxClass === (string) $taxClass->id)>
                                    {{ $taxClass->code }} / {{ $taxClass->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('default_tax_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="text-muted fs-sm mt-1">Required when Enable Tax Calculation is Yes. Example: GST / Goods and Services Tax for an India merchant.</div>
                    </div>

                    <div>
                        <label class="form-label fw-semibold" for="prices_include_tax">
                            Prices Include Tax
                            <i class="ph-question tax-help-icon" data-bs-popup="tooltip" title="Stores whether entered product prices should be treated as tax-inclusive later. No calculation happens in this step."></i>
                        </label>
                        <select name="prices_include_tax" id="prices_include_tax" class="form-select @error('prices_include_tax') is-invalid @enderror">
                            <option value="1" @selected((bool) $value('prices_include_tax', true))>Yes</option>
                            <option value="0" @selected(! (bool) $value('prices_include_tax', true))>No</option>
                        </select>
                        @error('prices_include_tax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="text-muted fs-sm mt-1">Yes: Rs 118 already includes GST. No: Rs 100 plus GST.</div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between gap-2">
                <a href="{{ route('merchant.settings.edit') }}" class="btn btn-light">
                    <i class="ph-arrow-left me-1"></i>
                    Back to Settings
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-floppy-disk me-1"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const taxEnabled = document.getElementById('tax_enabled');
            const warning = document.querySelector('.js-tax-enabled-warning');

            const toggleWarning = () => {
                warning?.classList.toggle('d-none', taxEnabled?.value !== '1');
            };

            taxEnabled?.addEventListener('change', toggleWarning);
            toggleWarning();
        });
    </script>
@endpush
