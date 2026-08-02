@php
    $dataTableOrder = ($tableId ?? '') === 'cancellation-reasons-trash-table'
        ? [[4, 'asc'], [0, 'asc']]
        : [[7, 'asc'], [0, 'asc']];
@endphp

<script>
    document.addEventListener('DOMContentLoaded', function () {
        @isset($tableId)
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

                jQuery('#{{ $tableId }}').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: @json($dataTableOrder),
                    columnDefs: [
                        { orderable: false, targets: -1 },
                        { responsivePriority: 1, targets: 0 },
                        { responsivePriority: 2, targets: -1 },
                    ],
                });
            }
        @endisset

        document.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.js-delete-cancellation-reason');

            if (deleteButton) {
                const form = document.getElementById(deleteButton.dataset.formId);

                if (!form) {
                    return;
                }

                bootbox.confirm({
                    title: 'Delete Cancellation Reason',
                    message: 'Are you sure you want to delete this cancellation reason?',
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
            }

            const restoreButton = event.target.closest('.js-restore-cancellation-reason');

            if (restoreButton) {
                const form = restoreButton.closest('form');

                if (!form) {
                    return;
                }

                bootbox.confirm({
                    title: 'Restore Cancellation Reason',
                    message: 'Restore this cancellation reason?',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Restore',
                            className: 'btn-success',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            }
        });
    });
</script>
