{{-- Purpose: Merchant dashboard using active-shop-scoped metrics and Limitless UI patterns. --}}
@extends('layouts.merchant')

@section('title', 'Merchant Dashboard | WindowShop')

@section('page_title', 'Merchant Dashboard')

@section('content')
    @php
        $stats = $dashboard['stats'];
        $latestOrders = $dashboard['latest_orders'];
        $recentActivities = $dashboard['recent_activities'];
        $activeShopLabel = $merchantActiveShopContext['activeShopLabel'] ?? 'Active shop';
        $formatElapsed = static function (int $seconds): string {
            if ($seconds < 1) {
                return '-';
            }

            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $remainingSeconds = $seconds % 60;

            return $hours > 0
                ? sprintf('%dh %02dm %02ds', $hours, $minutes, $remainingSeconds)
                : sprintf('%dm %02ds', $minutes, $remainingSeconds);
        };
        $cards = [
            ['label' => "Today's Orders", 'value' => number_format($stats['todays_orders']), 'icon' => 'ph-shopping-bag-open', 'color' => 'text-info'],
            ['label' => 'Pending Orders', 'value' => number_format($stats['pending_orders']), 'icon' => 'ph-clock-countdown', 'color' => 'text-warning'],
            ['label' => 'Revenue Today', 'value' => 'INR '.number_format($stats['revenue_today']), 'icon' => 'ph-currency-inr', 'color' => 'text-teal'],
            ['label' => 'Products', 'value' => number_format($stats['products']), 'icon' => 'ph-package', 'color' => 'text-success'],
            ['label' => 'Out of Stock', 'value' => number_format($stats['out_of_stock']), 'icon' => 'ph-warning-circle', 'color' => 'text-danger'],
            ['label' => 'Offers', 'value' => number_format($stats['active_offers']), 'icon' => 'ph-tag', 'color' => 'text-pink'],
            ['label' => 'Customers', 'value' => number_format($stats['customers']), 'icon' => 'ph-users-three', 'color' => 'text-purple'],
            ['label' => 'Shops', 'value' => number_format($stats['total_shops']), 'icon' => 'ph-storefront', 'color' => 'text-primary'],
        ];
    @endphp

    <div class="row">
        @foreach ($cards as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="card card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="{{ $card['icon'] }} ph-2x {{ $card['color'] }}"></i>
                        </div>
                        <div class="flex-fill text-end">
                            <h5 class="mb-0">{{ $card['value'] }}</h5>
                            <span class="text-muted">{{ $card['label'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h5 class="mb-0">Latest Orders</h5>
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-auto">{{ $activeShopLabel }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Shop</th>
                                <th>Total</th>
                                <th>Time Used</th>
                                <th>Status</th>
                                <th class="text-end">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latestOrders as $order)
                                <tr>
                                    <td><a href="#">#{{ $order->order_number ?? $order->id }}</a></td>
                                    <td>{{ $order->customer_name ?? 'Customer' }}</td>
                                    <td>{{ $activeShopLabel }}</td>
                                    <td>INR {{ number_format((float) ($order->total_amount ?? $order->grand_total ?? $order->amount ?? 0)) }}</td>
                                    <td>{{ $formatElapsed((int) ($order->elapsed_seconds ?? 0)) }}</td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info">{{ Str::headline($order->status ?? 'new') }}</span></td>
                                    <td class="text-end text-muted">{{ isset($order->created_at) ? \Illuminate\Support\Carbon::parse($order->created_at)->diffForHumans() : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Orders for the active shop will appear here.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>

                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-primary disabled">
                            <i class="ph-plus me-2"></i>
                            Add Product
                        </a>
                        <a href="#" class="btn btn-outline-primary disabled">
                            <i class="ph-tag me-2"></i>
                            Create Offer
                        </a>
                        <a href="#" class="btn btn-outline-primary disabled">
                            <i class="ph-receipt me-2"></i>
                            View Orders
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activities</h5>
                </div>

                <div class="card-body">
                    @foreach ($recentActivities as $activity)
                        <div class="d-flex {{ $loop->last ? '' : 'mb-3' }}">
                            <div class="me-3">
                                <i class="{{ $activity['icon'] }} text-{{ $activity['type'] }}"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $activity['title'] }}</div>
                                <div class="text-muted fs-sm">{{ $activity['description'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if (config('app.debug'))
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="mb-0">Session Debug</h5>
                <span class="badge bg-warning bg-opacity-10 text-warning ms-auto">Development</span>
            </div>

            <div class="card-body">
                <pre class="bg-light border rounded p-3 mb-0 overflow-auto" style="max-height: 420px;"><code>{{ json_encode(session()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
            </div>
        </div>
    @endif
@endsection
