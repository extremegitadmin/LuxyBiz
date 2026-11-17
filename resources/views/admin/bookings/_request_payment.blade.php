{{-- Request online payment: staff creates a secure hosted payment link for the customer. --}}
@php
    $online = \App\Services\Payments\Gateway::isOnline();
    $payable = in_array($booking->status, \App\Services\Payments\PaymentSessionService::PAYABLE_STATUSES, true);
@endphp
<div class="card rounded-4">
    <div class="card-body p-4">
        <h5 class="mb-3 fw-semibold">Request online payment</h5>

        @if (session('payment_link'))
            <div class="alert alert-success border-0">
                <div class="fw-semibold mb-2">Payment link (valid for 23 hours)</div>
                <div class="input-group">
                    <input id="payment-link" class="form-control" value="{{ session('payment_link') }}" readonly>
                    <button type="button" class="btn btn-outline-secondary" id="copy-payment-link">Copy</button>
                    <a class="btn btn-primary" href="{{ session('payment_link') }}" target="_blank" rel="noopener noreferrer">Open</a>
                </div>
                <div class="form-text mt-2">Anyone with this link can pay for this booking. Send it only to the customer.</div>
            </div>
            <script>
                document.getElementById('copy-payment-link').addEventListener('click', function () {
                    var input = document.getElementById('payment-link');
                    input.select();
                    try { navigator.clipboard.writeText(input.value); } catch (e) { document.execCommand('copy'); }
                    this.textContent = 'Copied';
                });
            </script>
        @endif

        @php $open = $online ? \App\Services\Payments\PaymentSessionService::openLinks($booking) : collect(); @endphp
        @if ($open->isNotEmpty())
            @php
                $n = $open->count();
                $openText = $n . ' payment ' . \Illuminate\Support\Str::plural('link', $n) . ' for this booking ' . ($n === 1 ? 'is' : 'are')
                    . ' still open (' . $booking->currency . ' ' . number_format((float) $open->sum('amount'), 2) . ' in total).';
            @endphp
            <div class="alert alert-warning border-0">
                {{ $openText }}
                If the customer pays more than one, the booking is overpaid. Create another only if a different amount is needed.
            </div>
        @endif

        @if (! $online)
            <div class="text-secondary">
                Online payments are not set up yet. Choose a provider and save its keys under
                <a href="{{ route('admin.settings.gateway') }}">Payment gateway</a>.
                Until then, staff record payments made outside the system.
            </div>
        @elseif (! $payable)
            <div class="text-secondary">This booking is {{ str_replace('_', ' ', $booking->status) }}, so it cannot take a payment.</div>
        @elseif ($balance <= 0)
            <div class="text-secondary">Nothing left to pay on this booking.</div>
        @else
            <form method="POST" action="{{ route('admin.bookings.payment-link', $booking) }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-12 col-sm-5 col-lg-4">
                    <label class="form-label" for="pay-amount">Amount ({{ $booking->currency }})</label>
                    <input id="pay-amount" name="amount" type="number" step="0.01" min="0.5" max="{{ $balance }}"
                        value="{{ old('amount', number_format($balance, 2, '.', '')) }}" class="form-control @error('amount') is-invalid @enderror" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-sm-auto">
                    <button type="submit" class="btn btn-primary px-4">Create payment link</button>
                </div>
                <div class="col-12 form-text">Balance due: {{ $booking->currency }} {{ number_format($balance, 2) }}. The customer pays on the provider's secure page. Card details never reach this system.</div>
            </form>
        @endif
    </div>
</div>
