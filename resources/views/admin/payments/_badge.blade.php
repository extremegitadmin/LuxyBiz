@php
    $color = ['succeeded' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'secondary'][$value] ?? 'secondary';
@endphp
<span class="badge bg-{{ $color }}">{{ ucfirst((string) $value) }}</span>
