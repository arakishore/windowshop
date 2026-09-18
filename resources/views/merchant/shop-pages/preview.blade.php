@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Preview Shop Page"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Shop Pages' => route('merchant.shop-pages.index'), $page->title => null]"
        :action-url="route('merchant.shop-pages.edit', $page)"
        action-label="Edit Page"
        action-icon="ph-pencil-simple"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0">{{ $page->title }}</h5>
            <span class="text-muted">{{ $merchantActiveShopContext['activeShopLabel'] ?? $shop->name }}</span>
        </div>
        <div class="card-body">
            <div class="mb-3"><span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($page->status) }}</span></div>
            <div style="overflow-wrap: anywhere;">{!! app(\App\Services\Merchant\ShopPageContent::class)->render($page->body) !!}</div>
        </div>
    </div>
@endsection
