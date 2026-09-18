@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Shop Pages"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Shop Pages' => null]"
        :action-url="route('merchant.shop-pages.create')"
        action-label="Add New Page"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0">Shop Pages for {{ $shop->name }}</h5>
            <span class="text-muted">{{ $merchantActiveShopContext['activeShopLabel'] ?? $shop->name }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Page</th>
                        <th>Type</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td><a class="fw-semibold text-body text-decoration-none" href="{{ route('merchant.shop-pages.edit', $page) }}">{{ $page->title }}</a></td>
                            <td><span class="badge {{ $page->page_type === 'standard' ? 'bg-secondary bg-opacity-10 text-secondary' : 'bg-info bg-opacity-10 text-info' }}">{{ ucfirst($page->page_type) }}</span></td>
                            <td><code>{{ $page->slug }}</code></td>
                            <td><span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($page->status) }}</span></td>
                            <td>{{ app_datetime($page->updated_at) }}</td>
                            <td class="text-center">
                                <div class="list-icons justify-content-center">
                                    <a href="{{ route('merchant.shop-pages.edit', $page) }}" class="list-icons-item text-primary" title="Edit" aria-label="Edit {{ $page->title }}"><i class="ph-pencil-simple"></i></a>
                                    <a href="{{ route('merchant.shop-pages.preview', $page) }}" class="list-icons-item text-info" title="Preview" aria-label="Preview {{ $page->title }}"><i class="ph-eye"></i></a>
                                    @if ($page->page_type === 'custom')
                                        <form method="POST" action="{{ route('merchant.shop-pages.destroy', $page) }}" class="d-inline" onsubmit="return confirm('Delete this custom page?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="list-icons-item text-danger border-0 bg-transparent p-0" title="Delete" aria-label="Delete {{ $page->title }}"><i class="ph-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($pages->hasPages())
            <div class="card-body">{{ $pages->links('pagination::admin-datatable') }}</div>
        @endif
    </div>
@endsection
