@php
    $color = match ($case->status) {
        'pending' => 'yellow',
        'published' => 'blue',
        'rejected' => 'red',
        'found' => 'green',
    };
@endphp
<span class="badge bg-{{ $color }}-lt">{{ \App\Models\MissingCase::STATUSES[$case->status] }}</span>
