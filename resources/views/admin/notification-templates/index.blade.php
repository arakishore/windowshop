@extends('layouts.admin')
@section('title', 'Notification Templates | '.$marketplaceName)
@section('page_title', 'Notification Templates')
@section('content')
@php
    $preservedFilters = array_filter(Arr::only($filters, ['search', 'recipient', 'rule', 'status']), fn ($value) => filled($value));
@endphp
<div class="card"><div class="card-body pb-0"><ul class="nav nav-tabs nav-tabs-highlight mb-0">
    @foreach(['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channel => $label)
        <li class="nav-item"><a class="nav-link {{ $selectedChannel === $channel ? 'active' : '' }}" href="{{ route('admin.notification-templates.index', [...$preservedFilters, 'channel' => $channel]) }}">{{ $label }}</a></li>
    @endforeach
</ul></div></div>
@if($selectedChannel === 'sms')<div class="alert alert-info">SMS templates are available, but SMS delivery requires a configured provider.</div>@endif
@if($selectedChannel === 'whatsapp')<div class="alert alert-info">WhatsApp templates are available, but WhatsApp delivery requires a configured provider.</div>@endif
<div class="card"><div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="channel" value="{{ $selectedChannel }}">
        <div class="col-lg-4"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Notification name or event key"></div>
        @foreach(['recipient' => ['customer'=>'Customer','merchant'=>'Merchant','admin'=>'Admin'], 'rule' => ['mandatory'=>'Mandatory','configurable'=>'Configurable','optional'=>'Optional'], 'status' => ['active'=>'Active','inactive'=>'Inactive']] as $name => $options)
            <div class="col-lg-2"><label class="form-label">{{ ucfirst($name) }}</label><select name="{{ $name }}" class="form-select"><option value="">All</option>@foreach($options as $value => $label)<option value="{{ $value }}" @selected(($filters[$name] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        @endforeach
        <div class="col-12"><button class="btn btn-primary">Apply</button> <a href="{{ route('admin.notification-templates.index', ['channel' => $selectedChannel]) }}" class="btn btn-light">Reset</a></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Notification</th><th>Recipient</th><th>Rule</th><th>Default</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($rows as $row)<tr>
        <td><div class="fw-semibold">{{ $row['event']->label }}</div><small class="text-muted">{{ $row['event']->key }}</small></td>
        <td>{{ ucfirst($row['event']->audience) }}</td>
        <td><span class="badge {{ $row['rule'] === 'mandatory' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-primary bg-opacity-10 text-primary' }}">{{ ucfirst($row['rule']) }} @if($row['rule'] === 'mandatory') 🔒 @endif</span></td>
        <td>{{ $row['event']->defaultEnabled($row['template']->channel) ? 'ON' : 'OFF' }}</td><td>{{ $row['template']->is_active ? 'Active' : 'Inactive' }}</td>
        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.notification-templates.edit', $row['template']) }}">Edit</a></td>
    </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No notification templates match the filters.</td></tr>@endforelse</tbody>
</table></div></div>
@endsection
