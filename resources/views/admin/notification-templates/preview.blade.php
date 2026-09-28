@extends('layouts.admin')
@section('title', 'Email Preview | '.$marketplaceName)
@section('page_title', 'Email Preview')
@section('content')
<div class="card"><div class="card-header d-flex justify-content-between"><div><h5 class="mb-0">{{ $rendered['subject'] }}</h5><small class="text-muted">Preview only — no email was sent.</small></div><a href="{{ route('admin.notification-templates.edit', $notificationTemplate) }}" class="btn btn-light">Back to Edit</a></div><div class="card-body"><iframe title="Email preview" srcdoc="{{ $html }}" style="width:100%;min-height:650px;border:1px solid #ddd"></iframe></div></div>
@endsection
