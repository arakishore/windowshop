@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Postal Code Details"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Codes' => route('admin.master.postal-codes.index'), $postalCode->postal_code => null]"
        :action-url="route('admin.master.postal-codes.edit', $postalCode)"
        action-label="Edit Postal Code"
        action-icon="ph-pencil-simple"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ $postalCode->postal_code }} - {{ $postalCode->office_name }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach([
                    'ID' => $postalCode->getKey(),
                    'Postal Code' => $postalCode->postal_code,
                    'Office / Place Name' => $postalCode->office_name,
                    'Office Type' => $postalCode->office_type,
                    'Delivery Status' => $postalCode->delivery_status,
                    'Shipping' => $postalCode->shipping_enabled ? 'Yes' : 'No',
                    'District' => $postalCode->district,
                    'State' => $postalCode->state,
                    'Circle' => $postalCode->circle_name,
                    'Region' => $postalCode->region_name,
                    'Division' => $postalCode->division_name,
                    'Latitude' => $postalCode->latitude,
                    'Longitude' => $postalCode->longitude,
                    'Status' => ucfirst($postalCode->status),
                    'Created' => app_datetime($postalCode->created_at),
                    'Updated' => app_datetime($postalCode->updated_at),
                ] as $label => $value)
                    <div class="col-md-4">
                        <div class="text-muted fs-sm">{{ $label }}</div>
                        <div class="fw-semibold">{{ $value ?: '-' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
