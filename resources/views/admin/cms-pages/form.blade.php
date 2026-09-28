@extends('layouts.admin')

@php
    $editing = $page->exists;
    $standard = $page->page_type === \App\Models\CmsPage::TYPE_STANDARD;
@endphp

@section('breadcrumb')
    <x-page-header
        :title="$editing ? 'Edit Marketplace Page' : 'Add Marketplace Page'"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Content Management' => null, 'Pages' => route('admin.cms-pages.index'), ($editing ? $page->title : 'Add New Page') => null]"
        :action-url="$editing ? route('admin.cms-pages.preview', $page) : null"
        action-label="Preview Saved Page"
        action-icon="ph-eye"
    />
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('admin.cms-pages.update', $page) : route('admin.cms-pages.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ $editing ? $page->title : 'New Custom Page' }}</h5>
                <span class="text-muted">{{ $marketplaceName }} / Marketplace Pages</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @if ($standard)
                        <div class="col-12"><span class="badge bg-secondary bg-opacity-10 text-secondary">Standard</span> <code class="ms-2">{{ $page->slug }}</code></div>
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
                        <label for="body" class="form-label">Content</label>
                        <textarea id="body" name="body" rows="18" maxlength="50000" class="form-control js-cms-editor @error('body') is-invalid @enderror">{{ old('body', $page->body) }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($editing)
                        <div class="col-12"><span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($page->status) }}</span></div>
                    @endif
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end flex-wrap gap-2">
                <a href="{{ route('admin.cms-pages.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" name="status" value="draft" class="btn btn-outline-secondary"><i class="ph-file-text me-2"></i>{{ $editing && $page->status === 'published' ? 'Move to Draft' : 'Save Draft' }}</button>
                <button type="submit" name="status" value="published" class="btn btn-primary"><i class="ph-check-circle me-2"></i>{{ $editing && $page->status === 'published' ? 'Save Published Page' : 'Publish' }}</button>
            </div>
        </div>
    </form>
@endsection

@include('partials.cms-editor')
