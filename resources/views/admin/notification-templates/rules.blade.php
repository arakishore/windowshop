@extends('layouts.admin')
@section('title', 'Notification Rules | '.$marketplaceName)
@section('page_title', 'Notification Rules')
@section('content')
<div class="card"><div class="card-body text-muted">Catalogue policy is authoritative. Merchant-configurable defaults are shown here; per-shop and merchant overrides remain in their existing settings.</div></div>
<div class="card"><div class="table-responsive"><table id="notification-rules-table" class="table table-hover align-middle mb-0"><thead><tr><th>Notification</th><th>Recipient</th><th>Email</th><th>SMS</th><th>WhatsApp</th><th>Rule / Control</th></tr></thead><tbody>
@foreach($events as $event)<tr><td><div class="fw-semibold">{{ $event->label }}</div><small class="text-muted">{{ $event->key }}</small></td><td>{{ ucfirst($event->audience) }}</td>
@foreach(['email','sms','whatsapp'] as $channel)<td>@if($event->supports($channel))<span class="badge {{ $event->defaultEnabled($channel) ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-body' }}">{{ $event->defaultEnabled($channel) ? 'ON' : 'OFF' }} @if($event->mandatory($channel)) 🔒 @endif</span>@else—@endif</td>@endforeach
<td>@if($event->mandatory('email'))Mandatory / locked @elseif($event->preferenceScope === 'global' && $event->supports('email'))<form method="POST" action="{{ route('admin.notification-rules.global.update') }}" class="d-flex gap-2">@csrf @method('PUT')<input type="hidden" name="event_key" value="{{ $event->key }}"><select name="enabled" class="form-select form-select-sm"><option value="1" @selected($globalEmailEnabled[$event->key])>ON</option><option value="0" @selected(!$globalEmailEnabled[$event->key])>OFF</option></select><button class="btn btn-sm btn-outline-primary">Save</button></form>@else Merchant configurable (default {{ $event->defaultEnabled('email') ? 'ON' : 'OFF' }}) @endif</td></tr>@endforeach
</tbody></table></div></div>
@endsection

@push('vendor_scripts')
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/extensions/responsive.min.js') }}"></script>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery || !jQuery.fn.DataTable) {
                return;
            }

            jQuery('#notification-rules-table').DataTable({
                autoWidth: false,
                responsive: true,
                pageLength: 25,
                order: [[0, 'asc']],
                dom: '<"datatable-header"fl><"datatable-scroll"t><"datatable-footer"ip>',
                language: {
                    search: '<span class="me-3">Filter:</span> <div class="form-control-feedback form-control-feedback-end flex-fill">_INPUT_<div class="form-control-feedback-icon"><i class="ph-magnifying-glass opacity-50"></i></div></div>',
                    searchPlaceholder: 'Search notification rules...',
                    lengthMenu: '<span class="me-3">Show:</span> _MENU_',
                    paginate: {
                        next: document.dir === 'rtl' ? '&larr;' : '&rarr;',
                        previous: document.dir === 'rtl' ? '&rarr;' : '&larr;',
                    },
                },
                columnDefs: [
                    { orderable: false, targets: -1 },
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: -1 },
                ],
            });
        });
    </script>
@endpush
