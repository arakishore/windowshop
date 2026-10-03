@extends('layouts.merchant')

@section('title', 'Notification Settings | '.$marketplaceName)
@section('page_title', 'Notification Settings')

@section('content')
@php
    $shopNotificationEmail = old('email.shop_notification_email', $recipients['shop_notification_email']);
    $shopEmailEnabled = filter_var($shopNotificationEmail, FILTER_VALIDATE_EMAIL) !== false;
@endphp
<div class="card border-start border-start-4 border-start-primary">
    <div class="card-body">
        <h4 class="mb-1">Notification Settings</h4>
        <p class="text-muted mb-0">Manage who receives {{ $shop->name }}'s operational notifications.</p>
    </div>
</div>

<form method="POST" action="{{ route('merchant.notification-settings.update') }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Email Notifications</h5></div>
        <div class="card-body">
            <p class="text-muted">Configure the main mailbox and any supplementary recipients for {{ $shop->name }}'s notifications.</p>

            <div class="mb-4">
                <label for="shop_notification_email" class="form-label fw-semibold">Shop Notification Email</label>
                <input id="shop_notification_email" name="email[shop_notification_email]" type="email" value="{{ $shopNotificationEmail }}" class="form-control @error('email.shop_notification_email') is-invalid @enderror" autocomplete="email" data-shop-notification-email>
                <div class="form-text">Order and other important shop notifications for this shop will be sent to this email address.</div>
                <div class="form-text fw-semibold">Leave blank to disable shop email notifications.</div>
                @error('email.shop_notification_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @foreach(['additional_to' => ['Additional To', 'Additional recipients appear in the To field.'], 'cc' => ['CC', 'Recipients receive a visible copy.'], 'bcc' => ['BCC', 'Recipients receive a hidden copy.']] as $group => [$label, $help])
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ $label }}</label>
                    <div class="form-text mt-0 mb-2">{{ $help }}</div>
                    <input type="text" name="email[{{ $group }}]" value="{{ old('email.'.$group, implode(', ', $recipients[$group])) }}" class="form-control @error('email.'.$group) is-invalid @enderror @error('email.'.$group.'.*') is-invalid @enderror" placeholder="email@example.com, another@example.com" aria-label="{{ $label }} recipients" data-shop-notification-supplementary @readonly(! $shopEmailEnabled) aria-disabled="{{ $shopEmailEnabled ? 'false' : 'true' }}">
                    <div class="form-text">Separate multiple email addresses with commas.</div>
                    @error('email.'.$group)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('email.'.$group.'.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Customer Order Notifications</h5></div>
        <div class="card-body">
            <p class="text-muted">Choose which order status updates are emailed to your customers. Required notifications are managed by WindowShop.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Notification</th><th>Preference</th></tr></thead>
                    <tbody>
                    @foreach($customerOrderEvents as $event)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $event->label }}</div>
                                <div class="text-muted fs-sm">{{ $event->description }}</div>
                            </td>
                            <td style="min-width:240px">
                                <select class="form-select" name="notification_preferences[{{ $event->key }}]" aria-label="Preference for {{ $event->label }}">
                                    <option value="default" @selected($customerOrderPreferences[$event->key] === 'default')>Use WindowShop Default ({{ $customerOrderDefaults[$event->key] ? 'ON' : 'OFF' }})</option>
                                    <option value="on" @selected($customerOrderPreferences[$event->key] === 'on')>ON</option>
                                    <option value="off" @selected($customerOrderPreferences[$event->key] === 'off')>OFF</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button class="btn btn-primary mb-4" type="submit">Save Changes</button>
</form>

<div class="row">
    <div class="col-md-6"><div class="card h-100"><div class="card-header"><h5 class="mb-0">SMS Notifications</h5></div><div class="card-body text-muted">SMS notifications are not currently available.</div></div></div>
    <div class="col-md-6"><div class="card h-100"><div class="card-header"><h5 class="mb-0">WhatsApp Notifications</h5></div><div class="card-body text-muted">WhatsApp notifications are not currently available.</div></div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const primary = document.querySelector('[data-shop-notification-email]');
    const supplementary = document.querySelectorAll('[data-shop-notification-supplementary]');
    if (!primary) return;

    const synchronizeSupplementaryState = function () {
        const enabled = primary.value.trim() !== '' && primary.checkValidity();
        supplementary.forEach(function (input) {
            input.readOnly = !enabled;
            input.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        });
    };

    primary.addEventListener('input', synchronizeSupplementaryState);
    synchronizeSupplementaryState();
});
</script>
@endpush
