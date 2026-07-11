{{-- Purpose: Renders the approved Overview content using real merchant data only. --}}
@php
    $accountBadge = $accountStatusBadgeClasses[$merchant->status] ?? 'secondary';
    $verificationBadge = $verificationStatusBadgeClasses[$merchant->verification_status] ?? 'secondary';
    $flagLabel = function (?bool $value): string {
        if ($value === null) {
            return 'Not answered';
        }

        return $value ? 'Yes' : 'No';
    };
@endphp

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2 py-3">
                <span class="bg-primary bg-opacity-10 text-primary rounded d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="ph-buildings fs-4"></i>
                </span>
                <h5 class="mb-0">Business Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Business Name</td>
                            <td class="fw-semibold">{{ $merchant->business_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Legal Name</td>
                            <td>{{ $merchant->legal_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Business Type</td>
                            <td>{{ $merchant->business_type ? ($businessTypes[$merchant->business_type] ?? str_replace('_', ' ', ucfirst($merchant->business_type))) : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">GST Number</td>
                            <td>{{ $merchant->gst_number ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2 py-3">
                <span class="bg-purple bg-opacity-10 text-purple rounded d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="ph-user-circle fs-4"></i>
                </span>
                <h5 class="mb-0">Owner Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Owner Name</td>
                            <td class="fw-semibold">{{ $merchant->user?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email</td>
                            <td class="text-break">{{ $merchant->user?->email ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Mobile</td>
                            <td>{{ $merchant->user?->mobile ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Login</td>
                            <td>{{ $merchant->user?->last_login_at?->format('d M Y h:i A') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created On</td>
                            <td>{{ $merchant->created_at?->format('d M Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Updated</td>
                            <td>{{ $merchant->updated_at?->format('d M Y h:i A') ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2 py-3">
                <span class="bg-success bg-opacity-10 text-success rounded d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="ph-shield-check fs-4"></i>
                </span>
                <h5 class="mb-0">Merchant Status</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Account Status</td>
                            <td><span class="badge bg-{{ $accountBadge }}">{{ ucfirst($merchant->status) }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Verification Status</td>
                            <td><span class="badge bg-{{ $verificationBadge }}">{{ ucfirst($merchant->verification_status) }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Verified At</td>
                            <td>{{ $merchant->verified_at?->format('d M Y h:i A') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Verified By</td>
                            <td>{{ $merchant->verifiedBy?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Has Shop Licence?</td>
                            <td>{{ $flagLabel($merchant->has_shop_license) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Has FSSAI?</td>
                            <td>{{ $flagLabel($merchant->has_fssai) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Rejection Reason</td>
                            <td>{{ $merchant->verification_status === 'rejected' ? ($merchant->rejection_reason ?? '-') : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2 py-3">
                <span class="bg-info bg-opacity-10 text-info rounded d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="ph-lightning fs-4"></i>
                </span>
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('admin.merchants.edit', $merchant) }}" class="btn btn-primary">
                        <i class="ph-pencil-simple me-2"></i>
                        Edit Merchant
                    </a>
                    <a href="{{ route('admin.merchants.address', $merchant) }}" class="btn btn-light">
                        <i class="ph-map-pin me-2"></i>
                        Manage Owner Address
                    </a>
                    <a href="{{ route('admin.merchants.shops', $merchant) }}" class="btn btn-light">
                        <i class="ph-storefront me-2"></i>
                        Manage Shops
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Recent Activity</h5>
    </div>
    {{-- TODO: Replace this empty state when merchant audit-log readers are implemented. --}}
    <x-empty-state
        icon="ph-clock-counter-clockwise"
        title="No recent activity available"
        message="Merchant activity will appear here after application-wide audit logging is implemented."
    />
</div>
