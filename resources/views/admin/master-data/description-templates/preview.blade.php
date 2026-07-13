{{-- Purpose: Previews generated product descriptions from a category template. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Preview Description Template"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Description Templates' => route('admin.master.description-templates.index'), $template->name => null, 'Preview' => null]"
        :action-url="route('admin.master.description-templates.edit', $template)"
        action-label="Edit Template"
        action-icon="ph-pencil-simple"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please correct the highlighted fields.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-5">
            <form method="POST" action="{{ route('admin.master.description-templates.preview.generate', $template) }}" class="border rounded bg-white p-3">
                @csrf
                <div class="mb-3">
                    <div class="fw-semibold">{{ $template->name }}</div>
                    <div class="text-muted">{{ $template->category?->name ?? '-' }}</div>
                </div>

                <div class="row g-3">
                    @foreach($placeholders as $placeholder)
                        <div class="col-md-6">
                            <label for="{{ $placeholder }}" class="form-label">{{ \Illuminate\Support\Str::headline($placeholder) }}</label>
                            <input id="{{ $placeholder }}" name="{{ $placeholder }}" type="text" value="{{ old($placeholder, $values[$placeholder] ?? '') }}" class="form-control @error($placeholder) is-invalid @enderror">
                            @error($placeholder)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-arrows-clockwise me-2"></i>
                        Generate Preview
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xl-7">
            <div class="border rounded bg-white p-3 h-100">
                <h5 class="mb-3">Generated Output</h5>

                @if(! $preview['found'])
                    <div class="alert alert-warning mb-0">{{ $preview['message'] }}</div>
                @else
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea class="form-control" rows="4" readonly>{{ $preview['short_description'] }}</textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="16" readonly>{{ $preview['description'] }}</textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Meta Title</label>
                        <input type="text" class="form-control" value="{{ $preview['meta_title'] }}" readonly>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Meta Description</label>
                        <textarea class="form-control" rows="3" readonly>{{ $preview['meta_description'] }}</textarea>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
