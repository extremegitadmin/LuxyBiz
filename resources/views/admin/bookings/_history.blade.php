{{-- Change history card. Needs $changes (a collection of BookingChange with user loaded). --}}
<div class="card rounded-4">
    <div class="card-body p-4">
        <h5 class="mb-3 fw-semibold">Change history</h5>
        @forelse ($changes as $change)
            <div class="{{ $loop->last ? '' : 'border-bottom pb-3 mb-3' }}">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                    <span class="fw-semibold">{{ $change->user->name ?? 'System' }}</span>
                    <span class="text-secondary small">{{ $change->created_at?->format('d M Y, H:i') }}</span>
                </div>
                @if ($change->reason)
                    <div class="small mb-1"><span class="text-secondary">Reason:</span> {{ $change->reason }}</div>
                @endif
                <ul class="mb-0 ps-3 small">
                    @foreach ($change->summaryLines() as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-secondary mb-0">No changes have been recorded for this booking yet.</p>
        @endforelse
        <div class="form-text mt-3">Passport numbers and health notes are logged as "changed" only. Their values are never stored in the history.</div>
    </div>
</div>
