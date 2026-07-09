{{-- Purpose: Demonstrates the reusable admin layout and Blade components for the WindowShop dashboard. --}}
@extends('layouts.admin')

@section('title', 'Dashboard | WindowShop')

@section('content')
    <div class="alert alert-success">
        Welcome to WindowShop Admin
    </div>

    <x-page-header
        title="WindowShop Dashboard"
        subtitle="Reusable Limitless components are ready for future admin modules."
        :breadcrumbs="['Home' => url('/'), 'Dashboard' => null]"
    />

    <div class="row">
        <div class="col-sm-6 col-xl-3">
            <x-card title="Users">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-primary bg-opacity-10 text-primary lh-1 rounded-pill p-2">
                            <i class="ph-users-three"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-sm text-muted">Admin module</div>
                        <h5 class="mb-0">Ready</h5>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-card title="Shops">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-success bg-opacity-10 text-success lh-1 rounded-pill p-2">
                            <i class="ph-storefront"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-sm text-muted">Admin module</div>
                        <h5 class="mb-0">Ready</h5>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-card title="Products">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-warning bg-opacity-10 text-warning lh-1 rounded-pill p-2">
                            <i class="ph-package"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-sm text-muted">Admin module</div>
                        <h5 class="mb-0">Ready</h5>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-card title="Orders">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-info bg-opacity-10 text-info lh-1 rounded-pill p-2">
                            <i class="ph-shopping-cart"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-sm text-muted">Admin module</div>
                        <h5 class="mb-0">Ready</h5>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <x-card title="Reusable Layout Architecture">
        <x-slot:tools>
            <button type="button" class="btn btn-light btn-icon btn-sm rounded-pill">
                <i class="ph-dots-three"></i>
            </button>
        </x-slot:tools>

        <x-empty-state
            icon="ph-layout"
            title="Blade architecture created"
            message="Business modules can now extend this layout without changing the Limitless theme shell."
        />
    </x-card>
@endsection
