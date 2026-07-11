{{-- Purpose: Provides route-based merchant management tabs. --}}
@php
    $tabs = [
        'overview' => ['label' => 'Overview', 'route' => route('admin.merchants.show', $merchant)],
        'profile' => ['label' => 'Profile', 'route' => route('admin.merchants.edit', $merchant)],
        'address' => ['label' => 'Owner Address', 'route' => route('admin.merchants.address', $merchant)],
        'shops' => ['label' => 'Shops', 'route' => route('admin.merchants.shops.index', $merchant)],
    ];
@endphp

<div class="card">
    <div class="card-body p-0">
        <div class="overflow-auto">
            <ul class="nav nav-tabs nav-tabs-underline flex-nowrap mb-0 px-3">
                @foreach($tabs as $key => $tab)
                    <li class="nav-item">
                        <a href="{{ $tab['route'] }}" class="nav-link text-nowrap @if($activeTab === $key) active @endif">
                            {{ $tab['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
