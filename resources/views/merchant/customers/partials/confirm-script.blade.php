{{-- Purpose: Shared confirmation behaviour for customer destructive/status actions. --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-confirm-action');

            if (!button) {
                return;
            }

            const form = button.closest('.js-confirm-action-form');
            if (!form) {
                return;
            }

            bootbox.confirm({
                title: button.dataset.title,
                message: button.dataset.message,
                buttons: {
                    cancel: {
                        label: 'Cancel',
                        className: 'btn-link',
                    },
                    confirm: {
                        label: button.dataset.confirmLabel,
                        className: button.dataset.confirmClass,
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
