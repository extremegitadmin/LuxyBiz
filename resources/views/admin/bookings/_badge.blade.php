@php
    $maps = [
        'status' => ['pending' => 'warning', 'confirmed' => 'success', 'cancellation_requested' => 'danger', 'cancelled' => 'secondary', 'completed' => 'info'],
        'payment' => ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success', 'refunded' => 'secondary'],
    ];
    $color = $maps[$kind][$value] ?? 'secondary';
@endphp
<span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', (string) $value)) }}</span>
