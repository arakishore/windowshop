{{-- Purpose: Read-only merchant reference for active tax slabs configured by admin. --}}
@extends('layouts.merchant')

@section('title', 'Tax Slabs | WindowShop')

@section('page_title', 'Tax Slabs')

@push('styles')
    <style>
        .tax-slab-table th,
        .tax-slab-table td {
            vertical-align: middle;
        }

        .tax-component-list {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .tax-component-chip {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .2rem .45rem;
            border: 1px solid var(--border-color, #ddd);
            border-radius: .25rem;
            background: var(--gray-100, #f8f9fa);
            font-size: .78rem;
            line-height: 1.2;
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    @php
        $businessAddress = $merchant->businessAddress;
        $country = $businessAddress?->country;
        $state = $businessAddress?->state;
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-0">Tax Slabs</h5>
                <div class="text-muted fs-sm mt-1">Read-only list of active tax slabs configured by admin for your business country.</div>
            </div>
            <span class="badge bg-secondary bg-opacity-10 text-secondary">Reference only</span>
        </div>

        <div class="card-body">
            <div class="alert alert-light border mb-3">
                <div class="fw-semibold mb-1">Business tax profile</div>
                <div class="text-muted fs-sm">
                    Country: {{ $country?->name ?? 'Not set' }} |
                    State: {{ $state?->name ?? 'Not set' }}
                </div>
            </div>

            @forelse ($taxClasses as $taxClass)
                <div class="border rounded p-3 {{ $loop->last ? '' : 'mb-3' }}">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">{{ $taxClass->code }} / {{ $taxClass->name }}</div>
                            @if ($taxClass->description)
                                <div class="text-muted fs-sm">{{ $taxClass->description }}</div>
                            @endif
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                    </div>

                    @if ($taxClass->rates->isEmpty())
                        <div class="alert alert-info mb-0">No active slabs are configured for this tax class yet.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm tax-slab-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Slab</th>
                                        <th>Total Rate</th>
                                        <th>Effective</th>
                                        <th>Components</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($taxClass->rates as $rate)
                                        <tr>
                                            <td class="fw-semibold">{{ $rate->name }}</td>
                                            <td>{{ number_format((float) $rate->total_rate, 4) }}%</td>
                                            <td>
                                                {{ $rate->effective_from?->format('d M Y') ?? '-' }}
                                                -
                                                {{ $rate->effective_to?->format('d M Y') ?? 'Open' }}
                                            </td>
                                            <td>
                                                @if ($rate->components->isEmpty())
                                                    <span class="text-muted">No components</span>
                                                @else
                                                    <div class="tax-component-list">
                                                        @foreach ($rate->components as $component)
                                                            <span class="tax-component-chip">
                                                                <span class="fw-semibold">{{ $component->code }}</span>
                                                                <span>{{ number_format((float) $component->rate, 4) }}%</span>
                                                                @if ($component->jurisdiction_type)
                                                                    <span class="text-muted">({{ ucfirst($component->jurisdiction_type) }})</span>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @empty
                <div class="alert alert-info mb-0">
                    No active tax slabs are available for your business country yet. Please contact admin if you expect GST, VAT, or sales tax slabs here.
                </div>
            @endforelse
        </div>
    </div>
@endsection
