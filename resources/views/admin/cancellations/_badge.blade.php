@php
    $color = ['pending' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'refunded' => 'success', 'credited' => 'primary'][$value] ?? 'secondary';
    $label = ['credited' => 'Credit issued'][$value] ?? ucfirst((string) $value);
@endphp
<span class="badge bg-{{ $color }}">{{ $label }}</span>
