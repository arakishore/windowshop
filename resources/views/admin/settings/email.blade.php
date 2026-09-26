@extends('layouts.admin')

@section('title', 'Email Notifications | '.$marketplaceName)
@section('page_title', 'Email Notifications')

@section('content')
<div class="card">
    <div class="card-header"><h5 class="mb-0">Central SMTP Configuration</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.email-settings.update') }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Email Enabled</label>
                    <input type="hidden" name="enabled" value="0">
                    <select name="enabled" class="form-select"><option value="1" @selected(old('enabled', $emailSettings['enabled']))>Yes</option><option value="0" @selected(! old('enabled', $emailSettings['enabled']))>No</option></select>
                </div>
                <div class="col-md-6"><label class="form-label">Delivery Method</label><input class="form-control" value="SMTP" disabled></div>
                <div class="col-md-8"><label class="form-label">SMTP Host</label><input name="smtp[host]" value="{{ old('smtp.host', $emailSettings['host']) }}" class="form-control @error('smtp.host') is-invalid @enderror">@error('smtp.host')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">SMTP Port</label><input name="smtp[port]" type="number" min="1" max="65535" value="{{ old('smtp.port', $emailSettings['port']) }}" class="form-control @error('smtp.port') is-invalid @enderror">@error('smtp.port')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">Encryption</label><select name="smtp[encryption]" class="form-select">@foreach(['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $value => $label)<option value="{{ $value }}" @selected(old('smtp.encryption', $emailSettings['encryption']) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">SMTP Username</label><input name="smtp[username]" value="{{ old('smtp.username', $emailSettings['username']) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">SMTP Password</label><input name="smtp[password]" type="password" autocomplete="new-password" class="form-control"><div class="form-text">{{ $emailSettings['password_configured'] ? 'Configured — leave blank to keep it.' : 'Not configured.' }}</div></div>
                <div class="col-md-4"><label class="form-label">From Name</label><input name="from_name" value="{{ old('from_name', $emailSettings['from_name']) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">From Email</label><input name="from_email" type="email" value="{{ old('from_email', $emailSettings['from_email']) }}" class="form-control @error('from_email') is-invalid @enderror">@error('from_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">Reply-To Email</label><input name="reply_to" type="email" value="{{ old('reply_to', $emailSettings['reply_to']) }}" class="form-control @error('reply_to') is-invalid @enderror">@error('reply_to')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save Settings</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Send Test Email</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.email-settings.test') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-8"><label class="form-label">Test Email Recipient</label><input name="test_recipient" type="email" value="{{ old('test_recipient') }}" class="form-control @error('test_recipient') is-invalid @enderror">@error('test_recipient')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><button class="btn btn-outline-primary" type="submit">Send Test Email</button></div>
        </form>
    </div>
</div>
@endsection
