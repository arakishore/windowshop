{{-- Purpose: Lists product description templates using client-side DataTables. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Description Templates"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Description Templates' => null]"
        :action-url="route('admin.master.description-templates.create')"
        action-label="Create Template"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
    @endphp

    @if($templates->isEmpty())
        <x-empty-state icon="ph-article" title="No description templates found" message="Create a category-based template to generate reusable product descriptions." />
    @else
        <div class="table-responsive datatable-wrapper border rounded bg-white">
            <table id="description-templates-table" class="table datatable-basic table-bordered table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Template Name</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Sort Order</th>
                        <th>Updated Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($templates as $template)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $template->name }}</div>
                                <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($template->short_description_template, 90) }}</div>
                            </td>
                            <td>{{ $template->category?->name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $statusClasses[$template->status] ?? 'bg-secondary' }}">
                                    {{ ucfirst($template->status) }}
                                </span>
                            </td>
                            <td>{{ $template->sort_order }}</td>
                            <td>{{ $template->updated_at?->format('d M Y') }}</td>
                            <td class="text-center">
                                <div class="list-icons justify-content-center">
                                    <a href="{{ route('admin.master.description-templates.preview', $template) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="Preview">
                                        <i class="ph-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.master.description-templates.edit', $template) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                        <i class="ph-pencil-simple"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.master.description-templates.destroy', $template) }}" class="d-inline js-delete-description-template-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-description-template" data-bs-popup="tooltip" title="Delete">
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

                jQuery('#description-templates-table').DataTable({
                    pageLength: 25,
                    order: [[3, 'asc'], [0, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: -1 },
                    ],
                });
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-description-template');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-description-template-form');

                bootbox.confirm({
                    title: 'Delete Description Template',
                    message: 'Are you sure you want to delete this description template?',
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
