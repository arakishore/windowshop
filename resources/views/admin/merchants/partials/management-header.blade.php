{{-- Purpose: Shows the approved merchant overview header using real merchant data only. --}}
@php
    $accountBadge = $accountStatusBadgeClasses[$merchant->status] ?? 'bg-secondary';
    $verificationBadge = $verificationStatusBadgeClasses[$merchant->verification_status] ?? 'bg-secondary';
@endphp

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Merchant Details</h5>
    </div>

    <div class="card-body">
        <div class="row g-4 align-items-center">
            <div class="col-xl-7">
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <div class="bg-success bg-opacity-10 text-success rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width: 96px; height: 96px;">
                        <i class="ph-storefront display-6"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h3 class="mb-0">{{ $merchant->business_name }}</h3>
                            <span class="badge {{ $accountBadge }}">{{ ucfirst($merchant->status) }}</span>
                            <span class="badge {{ $verificationBadge }}">Verification: {{ \App\Enums\MerchantVerificationStatus::badgeLabelFor($merchant->verification_status) }}</span>
                        </div>

                        <div class="row g-2 text-muted small">
                            <div class="col-sm-6 col-lg-4 text-break">
                                <i class="ph-tag me-1"></i>
                                Merchant ID: #{{ $merchant->getKey() }}
                            </div>
                            <div class="col-sm-6 col-lg-8 text-break">
                                <i class="ph-identification-card me-1"></i>
                                UUID: {{ $merchant->uuid }}
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <i class="ph-user me-1"></i>
                                Owner: {{ $merchant->user?->name ?? '-' }}
                            </div>
                            <div class="col-sm-6 col-lg-4 text-break">
                                <i class="ph-envelope me-1"></i>
                                Email: {{ $merchant->user?->email ?? '-' }}
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <i class="ph-phone me-1"></i>
                                Mobile: {{ $merchant->user?->mobile ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="row g-3 border-start-xl ps-xl-4">
                    <div class="col-sm-6">
                        <div class="d-flex gap-2">
                            <i class="ph-briefcase text-muted mt-1"></i>
                            <div>
                                <div class="text-muted small">Business Type</div>
                                <div class="fw-semibold">{{ $merchant->business_type ? ($businessTypes[$merchant->business_type] ?? str_replace('_', ' ', ucfirst($merchant->business_type))) : '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-2">
                            <i class="ph-calendar text-muted mt-1"></i>
                            <div>
                                <div class="text-muted small">Joined On</div>
                                <div class="fw-semibold">{{ $merchant->created_at?->format('d M Y') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-2">
                            <i class="ph-clock text-muted mt-1"></i>
                            <div>
                                <div class="text-muted small">Last Login</div>
                                <div class="fw-semibold">{{ $merchant->user?->last_login_at?->format('d M Y h:i A') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-2">
                            <i class="ph-arrows-clockwise text-muted mt-1"></i>
                            <div>
                                <div class="text-muted small">Updated On</div>
                                <div class="fw-semibold">{{ $merchant->updated_at?->format('d M Y h:i A') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
