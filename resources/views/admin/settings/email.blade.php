@extends('layouts.admin')

@section('title', 'Email Notifications | '.$marketplaceName)
@section('page_title', 'Email Notifications')

@push('styles')
<style>
    .email-logo-settings {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 1fr);
        gap: 1rem;
        align-items: start;
    }

    .email-logo-preview {
        width: min(100%, 400px);
        min-height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        border: 1px solid var(--border-color, #ddd);
        border-radius: .375rem;
        padding: 1rem;
    }

    .email-logo-preview img {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
    }

    @media (max-width: 767.98px) {
        .email-logo-settings {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<form method="POST" action="{{ route('admin.email-settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
<div class="card">
    <div class="card-header"><h5 class="mb-0">Central SMTP Configuration</h5></div>
    <div class="card-body">
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
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Email Branding &amp; Footer</h5></div>
    <div class="card-body">
        <h6>Branding</h6>
        <div class="email-logo-settings mb-4">
            <div>
                <label class="form-label fw-semibold">Current Email Logo Preview</label>
                <div class="email-logo-preview">
                    <img id="email_logo_preview" src="{{ $emailPresentation['email_logo_url'] }}" data-current-src="{{ $emailPresentation['email_logo_url'] }}" alt="Email logo preview">
                </div>
                <div class="small text-muted mt-2">{{ $emailSettings['branding.logo_path'] ? 'Dedicated transactional email logo' : 'Marketplace logo fallback' }}</div>
            </div>
            <div>
                <label for="email_logo" class="form-label fw-semibold">Upload / Change Email Logo</label>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <label for="email_logo" class="btn btn-outline-primary btn-sm mb-0"><i class="ph-upload me-1"></i>Choose image</label>
                    <button type="button" class="btn btn-link btn-sm text-muted js-clear-email-logo-preview">Clear</button>
                </div>
                <input id="email_logo" name="email_logo" type="file" accept=".png,.jpg,.jpeg" class="d-none @error('email_logo') is-invalid @enderror">
                <div class="form-text">PNG or JPG/JPEG, up to 2 MB. The email layout preserves its aspect ratio.</div>
                @error('email_logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if($emailSettings['branding.logo_path'])
                    <div class="form-check mt-3"><input type="hidden" name="remove_email_logo" value="0"><input class="form-check-input" type="checkbox" name="remove_email_logo" value="1" id="remove_email_logo"><label class="form-check-label" for="remove_email_logo">Remove Email Logo</label><div class="form-text">Removing it restores the marketplace logo fallback.</div></div>
                @endif
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><label class="form-label">Show Marketplace Name</label><select name="branding[show_name]" class="form-select"><option value="1" @selected(old('branding.show_name', $emailSettings['branding.show_name']))>Yes</option><option value="0" @selected(! old('branding.show_name', $emailSettings['branding.show_name']))>No</option></select></div>
            <div class="col-md-4"><label class="form-label">Show Footer</label><select name="footer[show]" class="form-select"><option value="1" @selected(old('footer.show', $emailSettings['footer.show']))>Yes</option><option value="0" @selected(! old('footer.show', $emailSettings['footer.show']))>No</option></select></div>
        </div>

        <h6>Footer Benefits</h6>
        <div class="row g-3 mb-4">
            @foreach([1, 2, 3] as $number)
                <div class="col-md-4"><label class="form-label">Benefit {{ $number }}</label><input name="footer[benefit_{{ $number }}]" maxlength="80" value="{{ old('footer.benefit_'.$number, $emailSettings['footer.benefit_'.$number]) }}" class="form-control"></div>
            @endforeach
        </div>

        <h6>Social Media</h6>
        <div class="row g-3 mb-4">
            @foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'twitter' => 'X / Twitter', 'linkedin' => 'LinkedIn'] as $key => $label)
                <div class="col-md-4"><label class="form-label">{{ $label }} URL</label><input name="footer[social][{{ $key }}]" type="url" value="{{ old('footer.social.'.$key, $emailSettings['footer.social.'.$key]) }}" class="form-control @error('footer.social.'.$key) is-invalid @enderror">@error('footer.social.'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endforeach
        </div>

        <h6>Mobile Apps</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label">Google Play URL</label><input name="footer[apps][google_play]" type="url" value="{{ old('footer.apps.google_play', $emailSettings['footer.apps.google_play']) }}" class="form-control @error('footer.apps.google_play') is-invalid @enderror">@error('footer.apps.google_play')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Apple App Store URL</label><input name="footer[apps][app_store]" type="url" value="{{ old('footer.apps.app_store', $emailSettings['footer.apps.app_store']) }}" class="form-control @error('footer.apps.app_store') is-invalid @enderror">@error('footer.apps.app_store')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>

        <h6>Powered By</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Show Powered By</label><select name="footer[show_powered_by]" class="form-select"><option value="1" @selected(old('footer.show_powered_by', $emailSettings['footer.show_powered_by']))>Yes</option><option value="0" @selected(! old('footer.show_powered_by', $emailSettings['footer.show_powered_by']))>No</option></select></div>
            <div class="col-md-8"><label class="form-label">Powered By Text</label><input name="footer[powered_by_text]" maxlength="255" value="{{ old('footer.powered_by_text', $emailSettings['footer.powered_by_text']) }}" class="form-control @error('footer.powered_by_text') is-invalid @enderror">@error('footer.powered_by_text')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Allowed variable: @{{ marketplace_name }}. A blank value uses the safe default.</div></div>
        </div>
        <div class="form-text mt-3">Privacy Policy, Terms &amp; Conditions, and Contact use the existing public WindowShop pages.</div>
        <button class="btn btn-primary mt-3" type="submit">Save Settings</button>
    </div>
</div>
</form>

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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('email_logo');
        const preview = document.getElementById('email_logo_preview');
        const clear = document.querySelector('.js-clear-email-logo-preview');
        const remove = document.getElementById('remove_email_logo');
        let objectUrl = null;

        if (!input || !preview) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];

            if (!file || !/^image\/(jpeg|jpg|png)$/i.test(file.type)) {
                return;
            }

            if (remove) {
                remove.checked = false;
            }

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }

            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
        });

        clear?.addEventListener('click', () => {
            input.value = '';

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            preview.src = preview.dataset.currentSrc;
        });

        remove?.addEventListener('change', () => {
            if (remove.checked) {
                input.value = '';
            }
        });
    });
</script>
@endpush
