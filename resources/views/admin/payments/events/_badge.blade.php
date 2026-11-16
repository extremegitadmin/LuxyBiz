@php
    $color = ['processed' => 'success', 'received' => 'info', 'ignored' => 'warning', 'failed' => 'danger'][$value] ?? 'secondary';
@endphp
<span class="badge bg-{{ $color }}">{{ ucfirst((string) $value) }}</span>
