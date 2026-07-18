{{-- Purpose: Lists merchant-scoped customers with search and status filtering. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Customers"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Customers' => null]"
        :action-url="route('merchant.customers.create')"
        action-label="Add Customer"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $hasFilters = $filters['search'] !== '' || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0">Customer List</h5>
                <div class="text-muted small">Merchant-wide customer profiles</div>
            </div>
            <a href="#customer-filter-collapse" class="text-body collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="customer-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="customer-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('merchant.customers.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, mobile, email, or customer code">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $value => $status)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('merchant.customers.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($customers->isEmpty())
            <x-empty-state icon="ph-users-three" title="No customers found" message="Create a customer or adjust the current filters." />
        @else
            <form id="bulk-customer-form" method="POST" action="{{ route('merchant.customers.bulk-action') }}" class="js-bulk-customer-form">
                @csrf
                <div class="card-body border-bottom d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group" style="max-width: 280px;">
                        <label class="input-group-text" for="bulk_action">Bulk Actions</label>
                        <select id="bulk_action" name="action" class="form-select" required>
                            <option value="">Choose</option>
                            <option value="mark_active">Mark Active</option>
                            <option value="mark_inactive">Mark Inactive</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-light js-bulk-customer-submit">
                        <i class="ph-check-square-offset me-2"></i>
                        Apply
                    </button>
                    <span class="text-muted small js-selected-count">0 selected</span>
                </div>
            </form>

            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 44px;">
                                <input type="checkbox" class="form-check-input js-select-all-customers" aria-label="Select all customers">
                            </th>
                            <th>Customer</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" form="bulk-customer-form" class="form-check-input js-customer-checkbox" aria-label="Select {{ $customer->name }}">
                            </td>
                            <td>
                                <a href="{{ route('merchant.customers.show', $customer) }}" class="fw-semibold text-body text-decoration-none">{{ $customer->name }}</a>
                                <div><code>{{ $customer->customer_code }}</code></div>
                            </td>
                            <td>
                                <div>{{ $customer->mobile }}</div>
                                <div class="text-muted small">{{ $customer->mobile_country_code }}</div>
                            </td>
                            <td>{{ $customer->email ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $statuses[$customer->status]['badge_class'] ?? 'bg-secondary' }}">
                                    {{ $statuses[$customer->status]['label'] ?? ucfirst($customer->status) }}
                                </span>
                            </td>
                            <td>{{ $customer->orders_count }}</td>
                            <td>{{ $customer->created_at?->format('d M Y') }}</td>
                            <td class="text-center">
                                <div class="list-icons justify-content-center">
                                    <a href="{{ route('merchant.customers.show', $customer) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                        <i class="ph-eye"></i>
                                    </a>
                                    <a href="{{ route('merchant.customers.edit', $customer) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                        <i class="ph-pencil-simple"></i>
                                    </a>
                                    <form method="POST" action="{{ route('merchant.customers.destroy', $customer) }}" class="d-inline js-confirm-action-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-title="Delete Customer" data-message="Delete this customer?<br><br>Orders will keep their customer snapshot, but the customer profile will move to trash." data-confirm-label="Yes, Delete" data-confirm-class="btn-danger" data-bs-popup="tooltip" title="Delete">
                                            <i class="ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $customers->firstItem() }} to {{ $customers->lastItem() }} of {{ $customers->total() }} entries
                </div>
                {{ $customers->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    @include('merchant.customers.partials.confirm-script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bulkForm = document.querySelector('.js-bulk-customer-form');
            const selectAll = document.querySelector('.js-select-all-customers');
            const selectedCount = document.querySelector('.js-selected-count');
            const bulkMessages = {
                mark_active: {
                    title: 'Mark Customers Active',
                    message: 'Mark selected customers as Active?',
                    label: 'Yes, Mark Active',
                    className: 'btn-success',
                },
                mark_inactive: {
                    title: 'Mark Customers Inactive',
                    message: 'Mark selected customers as Inactive?',
                    label: 'Yes, Mark Inactive',
                    className: 'btn-warning',
                },
                delete: {
                    title: 'Delete Customers',
                    message: 'Delete selected customers?<br><br>Orders will keep their customer snapshots, but selected customer profiles will move to trash.',
                    label: 'Yes, Delete',
                    className: 'btn-danger',
                },
            };

            const customerCheckboxes = () => Array.from(document.querySelectorAll('.js-customer-checkbox'));

            const updateSelectedCount = () => {
                const selected = customerCheckboxes().filter((checkbox) => checkbox.checked).length;

                if (selectedCount) {
                    selectedCount.textContent = selected + ' selected';
                }

                if (selectAll) {
                    selectAll.checked = selected > 0 && selected === customerCheckboxes().length;
                    selectAll.indeterminate = selected > 0 && selected < customerCheckboxes().length;
                }
            };

            selectAll?.addEventListener('change', function () {
                customerCheckboxes().forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                updateSelectedCount();
            });

            document.addEventListener('change', function (event) {
                if (event.target.closest('.js-customer-checkbox')) {
                    updateSelectedCount();
                }
            });

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-bulk-customer-submit');

                if (!button || !bulkForm) {
                    return;
                }

                const action = bulkForm.querySelector('[name="action"]').value;
                const selected = customerCheckboxes().filter((checkbox) => checkbox.checked).length;

                if (!action || selected === 0) {
                    bootbox.alert('Please choose a bulk action and select at least one customer.');
                    return;
                }

                const config = bulkMessages[action];

                bootbox.confirm({
                    title: config.title,
                    message: config.message,
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: config.label,
                            className: config.className,
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            bulkForm.submit();
                        }
                    },
                });
            });

            updateSelectedCount();
        });
    </script>
@endpush
