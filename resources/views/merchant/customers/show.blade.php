{{-- Purpose: Shows merchant customer profile details, addresses, and order history. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Customer Profile"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Customers' => route('merchant.customers.index'), $customer->name => null]"
        :action-url="route('merchant.customers.edit', $customer)"
        action-label="Edit Customer"
        action-icon="ph-pencil-simple"
    />
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h5 class="mb-1">{{ $customer->name }}</h5>
                            <code>{{ $customer->customer_code }}</code>
                        </div>
                        <span class="badge {{ $statuses[$customer->status]['badge_class'] ?? 'bg-secondary' }}">
                            {{ $statuses[$customer->status]['label'] ?? ucfirst($customer->status) }}
                        </span>
                    </div>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="text-muted small">Orders</div>
                                <div class="fw-semibold">{{ $summary['orders_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="text-muted small">Addresses</div>
                                <div class="fw-semibold">{{ $summary['addresses_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="text-muted small">Spent</div>
                                <div class="fw-semibold">INR {{ number_format($summary['total_spent'], 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    @if($customer->status === \App\Models\MerchantCustomer::STATUS_ACTIVE)
                        <form method="POST" action="{{ route('merchant.customers.deactivate', $customer) }}" class="js-confirm-action-form">
                            @csrf
                            <button type="button" class="btn btn-warning js-confirm-action" data-title="Deactivate Customer" data-message="Deactivate this customer?" data-confirm-label="Yes, Deactivate" data-confirm-class="btn-warning">
                                <i class="ph-user-minus me-2"></i>
                                Deactivate
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('merchant.customers.activate', $customer) }}" class="js-confirm-action-form">
                            @csrf
                            <button type="button" class="btn btn-success js-confirm-action" data-title="Activate Customer" data-message="Activate this customer?" data-confirm-label="Yes, Activate" data-confirm-class="btn-success">
                                <i class="ph-user-plus me-2"></i>
                                Activate
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('merchant.customers.destroy', $customer) }}" class="js-confirm-action-form">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-danger js-confirm-action" data-title="Delete Customer" data-message="Delete this customer?<br><br>Orders will keep their customer snapshot, but the customer profile will move to trash." data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
                            <i class="ph-trash me-2"></i>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs nav-tabs-underline mb-0">
                        <li class="nav-item">
                            <a href="{{ route('merchant.customers.show', ['customer' => $customer, 'tab' => 'details']) }}" class="nav-link {{ $activeTab === 'details' ? 'active' : '' }}">
                                <i class="ph-user me-2"></i>
                                Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']) }}" class="nav-link {{ $activeTab === 'addresses' ? 'active' : '' }}">
                                <i class="ph-map-pin me-2"></i>
                                Addresses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.customers.show', ['customer' => $customer, 'tab' => 'orders']) }}" class="nav-link {{ $activeTab === 'orders' ? 'active' : '' }}">
                                <i class="ph-receipt me-2"></i>
                                Orders
                            </a>
                        </li>
                    </ul>
                </div>

                @if($activeTab === 'details')
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Mobile</dt>
                            <dd class="col-sm-8">{{ $customer->mobile_country_code }} {{ $customer->mobile }}</dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8">{{ $customer->email ?: '-' }}</dd>
                            <dt class="col-sm-4">DOB</dt>
                            <dd class="col-sm-8">{{ $customer->date_of_birth?->format('d M Y') ?? '-' }}</dd>
                            <dt class="col-sm-4">Gender</dt>
                            <dd class="col-sm-8">{{ $customer->gender ? str_replace('_', ' ', ucfirst($customer->gender)) : '-' }}</dd>
                            @if($customer->is_business_customer)
                                <dt class="col-sm-4">Company</dt>
                                <dd class="col-sm-8">{{ $customer->company_name ?: '-' }}</dd>
                                <dt class="col-sm-4">GST</dt>
                                <dd class="col-sm-8">{{ $customer->gst_number ?: '-' }}</dd>
                            @endif
                            <dt class="col-sm-4">Linked User</dt>
                            <dd class="col-sm-8">{{ $customer->user?->email ?? '-' }}</dd>
                        </dl>
                        @if($customer->notes)
                            <hr>
                            <h6>Notes</h6>
                            <div>{!! nl2br(e($customer->notes)) !!}</div>
                        @endif
                    </div>
                @elseif($activeTab === 'addresses')
                    <div class="card-header border-top d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Addresses</h5>
                        <a href="{{ route('merchant.customers.addresses.create', $customer) }}" class="btn btn-primary btn-sm">
                            <i class="ph-plus me-2"></i>
                            Add Address
                        </a>
                    </div>
                    @if($addresses->isEmpty())
                        <x-empty-state icon="ph-map-pin" title="No addresses found" message="Address creation is optional. POS counter sales can continue without an address." />
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Address</th>
                                        <th>Recipient</th>
                                        <th>Defaults</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($addresses as $address)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $address->label }}</div>
                                                <div>{{ $address->address_line_1 }}</div>
                                                <div class="text-muted small">
                                                    {{ collect([$address->city?->name, $address->state?->name, $address->country?->name, $address->postal_code])->filter()->implode(', ') }}
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $address->recipient_name }}</div>
                                                <div class="text-muted small">{{ $address->recipient_mobile_country_code }} {{ $address->recipient_mobile }}</div>
                                            </td>
                                            <td>
                                                @if($address->is_default_shipping)<span class="badge bg-info me-1">Shipping</span>@endif
                                                @if($address->is_default_billing)<span class="badge bg-primary">Billing</span>@endif
                                                @if(! $address->is_default_shipping && ! $address->is_default_billing)-@endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $addressStatuses[$address->status]['badge_class'] ?? 'bg-secondary' }}">
                                                    {{ $addressStatuses[$address->status]['label'] ?? ucfirst($address->status) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="list-icons justify-content-center">
                                                    <a href="{{ route('merchant.customers.addresses.edit', [$customer, $address]) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                        <i class="ph-pencil-simple"></i>
                                                    </a>
                                                    <form method="POST" action="{{ route('merchant.customers.addresses.destroy', [$customer, $address]) }}" class="d-inline js-confirm-action-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-title="Delete Address" data-message="Delete this customer address?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger" data-bs-popup="tooltip" title="Delete">
                                                            <i class="ph-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <div class="card-header border-top">
                        <h5 class="mb-0">Order History</h5>
                    </div>
                    @if($orders->isEmpty())
                        <x-empty-state icon="ph-receipt" title="No orders found" message="Customer orders will appear here once linked." />
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orders as $order)
                                        <tr>
                                            <td class="fw-semibold">{{ $order->order_number }}</td>
                                            <td>{{ $order->created_at?->format('d M Y h:i A') }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</td>
                                            <td>{{ ucfirst($order->payment_method) }}</td>
                                            <td class="text-end">INR {{ number_format((float) $order->grand_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-body">
                            {{ $orders->onEachSide(1)->links('pagination::admin-datatable') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('merchant.customers.partials.confirm-script')
@endpush
