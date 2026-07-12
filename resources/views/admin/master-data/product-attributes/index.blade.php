{{-- Purpose: Lists product attribute groups using client-side DataTables. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Product Attributes"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Attributes' => null]"
        :action-url="route('admin.master.product-attributes.create')"
        action-label="Create Attribute"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
    @endphp

    @if($groups->isEmpty())
        <x-empty-state icon="ph-list-bullets" title="No product attributes found" message="Create an attribute group to start adding reusable product values." />
    @else
        <div class="table-responsive datatable-wrapper border rounded bg-white">
            <table id="product-attributes-table" class="table datatable-basic table-bordered table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Number of Values</th>
                        <th>Status</th>
                        <th>Sort Order</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $group)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $group->name }}</div>
                                @if($group->description)
                                    <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($group->description, 80) }}</div>
                                @endif
                            </td>
                            <td><code>{{ $group->code }}</code></td>
                            <td><span class="badge bg-light text-body border">{{ $group->values_count }}</span></td>
                            <td>
                                <span class="badge {{ $statusClasses[$group->status] ?? 'bg-secondary' }}">
                                    {{ ucfirst($group->status) }}
                                </span>
                            </td>
                            <td>{{ $group->sort_order }}</td>
                            <td class="text-center">
                                <div class="list-icons justify-content-center">
                                    <a href="{{ route('admin.master.product-attributes.values.index', $group) }}" class="list-icons-item text-success" data-bs-popup="tooltip" title="Manage Values">
                                        <i class="ph-list-bullets"></i>
                                    </a>
                                    <a href="{{ route('admin.master.product-attributes.edit', $group) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                        <i class="ph-pencil-simple"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.master.product-attributes.destroy', $group) }}" class="d-inline js-delete-product-attribute-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-product-attribute" data-bs-popup="tooltip" title="Delete">
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
    @endif
@endsection

@push('vendor_scripts')
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/extensions/responsive.min.js') }}"></script>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && jQuery.fn.DataTable) {
                jQuery.extend(jQuery.fn.dataTable.defaults, {
                    autoWidth: false,
                    dom: '<"datatable-header"fl><"datatable-scroll"t><"datatable-footer"ip>',
                    language: {
                        search: '<span class="me-3">Filter:</span> <div class="form-control-feedback form-control-feedback-end flex-fill">_INPUT_<div class="form-control-feedback-icon"><i class="ph-magnifying-glass opacity-50"></i></div></div>',
                        searchPlaceholder: 'Type to filter...',
                        lengthMenu: '<span class="me-3">Show:</span> _MENU_',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: document.dir == 'rtl' ? '&larr;' : '&rarr;',
                            previous: document.dir == 'rtl' ? '&rarr;' : '&larr;',
                        },
                    },
                });

                jQuery('#product-attributes-table').DataTable({
                    responsive: true,
                    pageLength: 500,
                    order: [[4, 'asc'], [0, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: -1 },
                        { responsivePriority: 1, targets: 0 },
                        { responsivePriority: 2, targets: -1 },
                    ],
                });
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-product-attribute');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-product-attribute-form');

                bootbox.confirm({
                    title: 'Delete Product Attribute',
                    message: 'Are you sure you want to delete this product attribute group? Its values will also be deleted.',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Delete',
                            className: 'btn-danger',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });
        });
    </script>
@endpush
