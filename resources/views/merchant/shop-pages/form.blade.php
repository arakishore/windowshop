@extends('layouts.merchant')

@php
    $editing = $page->exists;
    $standard = $page->page_type === \App\Models\ShopPage::TYPE_STANDARD;
@endphp

@section('breadcrumb')
    <x-page-header
        :title="$editing ? 'Edit Shop Page' : 'Add New Page'"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Shop Pages' => route('merchant.shop-pages.index'), ($editing ? $page->title : 'Add New Page') => null]"
        :action-url="$editing ? route('merchant.shop-pages.preview', $page) : null"
        action-label="Preview Saved Page"
        action-icon="ph-eye"
    />
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('merchant.shop-pages.update', $page) : route('merchant.shop-pages.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ $editing ? $page->title : 'New Custom Page' }}</h5>
                <span class="text-muted">{{ $merchantActiveShopContext['activeShopLabel'] ?? $shop->name }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @if ($standard)
                        <div class="col-12">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Standard</span>
                            <code class="ms-2">{{ $page->slug }}</code>
                        </div>
                    @else
                        <div class="col-md-7">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input id="title" name="title" type="text" maxlength="180" required value="{{ old('title', $page->title) }}" class="form-control @error('title') is-invalid @enderror">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label for="slug" class="form-label">Slug</label>
                            <input id="slug" name="slug" type="text" maxlength="180" value="{{ old('slug', $page->slug) }}" placeholder="Generated from title" class="form-control @error('slug') is-invalid @enderror">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-12">
                        <label for="body" class="form-label">{{ $standard && $page->page_key === 'policies' ? 'Additional Policy Notes' : 'Content' }}</label>
                        <textarea id="body" name="body" rows="18" maxlength="50000" class="form-control js-shop-page-editor @error('body') is-invalid @enderror">{{ old('body', $page->body) }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($standard && $page->page_key === 'policies')
                        <div class="col-12">
                            <div class="alert alert-info mb-0">Use this section for additional shop policy information. Delivery, payment, refund and exchange rules are managed in <a href="{{ route('merchant.settings.edit') }}" class="alert-link">Shop Settings</a>.</div>
                        </div>
                    @endif
                    @if ($editing)
                        <div class="col-12"><span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($page->status) }}</span></div>
                    @endif
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end flex-wrap gap-2">
                <a href="{{ route('merchant.shop-pages.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" name="status" value="draft" class="btn btn-outline-secondary"><i class="ph-file-text me-2"></i>{{ $editing && $page->status === 'published' ? 'Move to Draft' : 'Save Draft' }}</button>
                <button type="submit" name="status" value="published" class="btn btn-primary"><i class="ph-check-circle me-2"></i>{{ $editing && $page->status === 'published' ? 'Save Published Page' : 'Publish' }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <link rel="stylesheet" href="{{ asset('assets/admin/js/vendor/editors/ckeditor5-48.5.1/ckeditor5/ckeditor5.css') }}">
    <script src="{{ asset('assets/admin/js/vendor/editors/ckeditor5-48.5.1/ckeditor5/ckeditor5.umd.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const textarea = document.querySelector('.js-shop-page-editor');

            if (!textarea || !window.CKEDITOR?.ClassicEditor) {
                return;
            }

            const {
                ClassicEditor,
                Autoformat,
                BlockQuote,
                Bold,
                Essentials,
                Heading,
                Italic,
                Link,
                List,
                Paragraph,
                Table,
                TableToolbar,
                Undo,
            } = window.CKEDITOR;

            ClassicEditor
                .create(textarea, {
                    licenseKey: 'GPL',
                    plugins: [
                        Autoformat,
                        BlockQuote,
                        Bold,
                        Essentials,
                        Heading,
                        Italic,
                        Link,
                        List,
                        Paragraph,
                        Table,
                        TableToolbar,
                        Undo,
                    ],
                    toolbar: {
                        items: [
                            'undo',
                            'redo',
                            '|',
                            'heading',
                            '|',
                            'bold',
                            'italic',
                            'link',
                            '|',
                            'bulletedList',
                            'numberedList',
                            'blockQuote',
                            'insertTable',
                        ],
                    },
                    table: {
                        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
                    },
                })
                .then((editor) => {
                    textarea.form?.addEventListener('submit', () => {
                        textarea.value = editor.getData();
                    });
                })
                .catch((error) => {
                    console.error(error);
                });
        });
    </script>
@endpush
