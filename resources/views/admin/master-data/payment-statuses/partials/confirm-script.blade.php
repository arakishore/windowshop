<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-confirm-action');

            if (!button) {
                return;
            }

            const form = button.closest('.js-confirm-form');

            if (!form) {
                return;
            }

            bootbox.confirm({
                title: button.dataset.title || 'Confirm Action',
                message: button.dataset.message || 'Continue?',
                buttons: {
                    cancel: {
                        label: 'Cancel',
                        className: 'btn-link',
                    },
                    confirm: {
                        label: button.dataset.confirmLabel || 'Confirm',
                        className: button.dataset.confirmClass || 'btn-primary',
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
