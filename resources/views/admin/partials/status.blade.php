
@php
    $status = strtolower($status ?? 'draft');

    $styles = [
        'published' => 'background:#dcfce7;color:#166534;',
        'draft'     => 'background:#fef3c7;color:#92400e;',
        'archived'  => 'background:#fee2e2;color:#991b1b;',
        'active'    => 'background:#dcfce7;color:#166534;',
        'inactive'  => 'background:#f3f4f6;color:#374151;',
    ];

    $style = $styles[$status] ?? 'background:#e5e7eb;color:#374151;';
@endphp

<span style="{{ $style }} padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block;">
    {{ ucfirst($status) }}
</span>
