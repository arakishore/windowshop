{{-- Purpose: Shows merchant profile, owner, and read-only child record summaries. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="{{ $merchant->business_name }}"
        subtitle="Merchant profile details"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => null]"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'warning', 'deleted' => 'danger'];
        $verificationClasses = ['pending' => 'secondary', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger', 'suspended' => 'warning'];
    @endphp

    <div class="d-flex justify-content-end gap-2 mb-3">
        <a href="{{ route('admin.merchants.edit', $merchant) }}" class="btn btn-primary">
            <i class="ph-pencil-simple me-2"></i>
            Edit
        </a>
        <form method="POST" action="{{ route('admin.merchants.destroy', $merchant) }}" onsubmit="return confirm('Delete this merchant? This will soft delete the merchant profile and owner account.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="ph-trash me-2"></i>
                Delete
            </button>
        </form>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Business Details</h5>
                </div>
                <div class="card-body">
                    <div class="row gy-3">
                        <div class="col-md-6">
                            <div class="text-muted fs-sm">Business name</div>
                            <div class="fw-semibold">{{ $merchant->business_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-sm">Legal name</div>
                            <div>{{ $merchant->legal_name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Business type</div>
                            <div>{{ $merchant->business_type ? str_replace('_', ' ', ucfirst($merchant->business_type)) : '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">GST number</div>
                            <div>{{ $merchant->gst_number ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">PAN number</div>
                            <div>{{ $merchant->pan_number ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Verification</div>
                            <span class="badge bg-{{ $verificationClasses[$merchant->verification_status] ?? 'secondary' }}">{{ ucfirst($merchant->verification_status) }}</span>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Account status</div>
                            <span class="badge bg-{{ $statusClasses[$merchant->status] ?? 'secondary' }}">{{ ucfirst($merchant->status) }}</span>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Verified at</div>
                            <div>{{ $merchant->verified_at?->format('d M Y h:i A') ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Contact Details</h5>
                </div>
                <div class="card-body">
                    <div class="row gy-3">
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Contact person</div>
                            <div>{{ $merchant->contact_person_name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Contact email</div>
                            <div>{{ $merchant->contact_email ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Website</div>
                            <div>{{ $merchant->website_url ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Contact mobile</div>
                            <div>{{ $merchant->contact_mobile ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Alternate mobile</div>
                            <div>{{ $merchant->alternate_mobile ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Created</div>
                            <div>{{ $merchant->created_at?->format('d M Y h:i A') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Verification History</h5>
                </div>
                @if($merchant->verifications->isEmpty())
                    <x-empty-state icon="ph-check-circle" title="No verification history" message="Status changes will appear here." />
                @else
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Old</th>
                                    <th>New</th>
                                    <th>Reviewed by</th>
                                    <th>Reviewed at</th>
                                    <th>Internal comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($merchant->verifications as $verification)
                                    <tr>
                                        <td>{{ $verification->old_status ?? '-' }}</td>
                                        <td>{{ $verification->new_status }}</td>
                                        <td>{{ $verification->reviewer?->name ?? '-' }}</td>
                                        <td>{{ $verification->reviewed_at?->format('d M Y h:i A') ?? '-' }}</td>
                                        <td>{{ $verification->admin_comment ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Owner User</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted fs-sm">Name</div>
                        <div class="fw-semibold">{{ $merchant->user?->name ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted fs-sm">Email</div>
                        <div>{{ $merchant->user?->email ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted fs-sm">Mobile</div>
                        <div>{{ $merchant->user?->mobile ?? '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted fs-sm">User status</div>
                        <span class="badge bg-{{ $statusClasses[$merchant->user?->status] ?? 'secondary' }}">{{ ucfirst($merchant->user?->status ?? 'unknown') }}</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="fs-3 fw-semibold">{{ $merchant->addresses_count }}</div>
                            <div class="text-muted">Addresses</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="fs-3 fw-semibold">{{ $merchant->documents_count }}</div>
                            <div class="text-muted">Documents</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="fs-3 fw-semibold">{{ $merchant->bank_accounts_count }}</div>
                            <div class="text-muted">Bank Accounts</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="fs-3 fw-semibold">{{ $merchant->verifications_count }}</div>
                            <div class="text-muted">Reviews</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Admin Note</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $merchant->admin_note ?? 'No internal note recorded.' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
