@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Preview Marketplace Page"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Content Management' => null, 'Pages' => route('admin.cms-pages.index'), $page->title => null]"
        :action-url="route('admin.cms-pages.edit', $page)"
        action-label="Edit Page"
        action-icon="ph-pencil-simple"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0">{{ $page->title }}</h5>
            <span class="text-muted">{{ $marketplaceName }} / Marketplace Pages</span>
        </div>
        <div class="card-body">
            <div class="mb-3"><span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($page->status) }}</span></div>
            <div style="overflow-wrap: anywhere;">{!! app(\App\Services\Merchant\ShopPageContent::class)->render($page->body) !!}</div>
        </div>
    </div>
@endsection
