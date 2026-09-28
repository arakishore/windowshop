@push('scripts')
    <link rel="stylesheet" href="{{ asset('assets/admin/js/vendor/editors/ckeditor5-48.5.1/ckeditor5/ckeditor5.css') }}">
    <script src="{{ asset('assets/admin/js/vendor/editors/ckeditor5-48.5.1/ckeditor5/ckeditor5.umd.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const textarea = document.querySelector('.js-cms-editor');

            if (!textarea || !window.CKEDITOR?.ClassicEditor) {
                return;
            }

            const {
                ClassicEditor, Autoformat, BlockQuote, Bold, Essentials, Heading,
                Italic, Link, List, Paragraph, Table, TableToolbar, Undo,
            } = window.CKEDITOR;

            ClassicEditor.create(textarea, {
                licenseKey: 'GPL',
                plugins: [
                    Autoformat, BlockQuote, Bold, Essentials, Heading, Italic,
                    Link, List, Paragraph, Table, TableToolbar, Undo,
                ],
                toolbar: {
                    items: [
                        'undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'link',
                        '|', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable',
                    ],
                },
                table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
            }).then((editor) => {
                textarea.form?.addEventListener('submit', () => {
                    textarea.value = editor.getData();
                });
            }).catch((error) => {
                console.error(error);
            });
        });
    </script>
@endpush
