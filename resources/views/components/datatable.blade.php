{{-- Purpose: Provides a reusable responsive table wrapper for future admin datatable screens. --}}
@props([
    'headers' => [],
    'empty' => 'No records found.',
])

<div class="table-responsive">
    <table {{ $attributes->merge(['class' => 'table']) }}>
        @if(! empty($headers))
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            @if(isset($slot) && trim($slot) !== '')
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ max(count($headers), 1) }}" class="text-center text-muted py-4">{{ $empty }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
