<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Secure payment - LuxyBiz</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f6f5; color: #1f2d27; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        main { background: #fff; border-radius: 16px; padding: 40px 32px; max-width: 460px; width: 100%; margin: 16px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 1.5rem; margin: 0 0 12px; }
        p { margin: 0 0 12px; line-height: 1.6; color: #44554d; }
        .amount { font-size: 2rem; font-weight: 700; color: #1f2d27; margin: 8px 0 20px; }
        .meta { color: #6b7a73; font-size: .95rem; margin-bottom: 4px; }
        button { background: #1f6f54; color: #fff; border: 0; border-radius: 10px; padding: 14px 28px; font-size: 1rem; cursor: pointer; width: 100%; }
        button:hover { background: #185a44; }
        .note { font-size: .85rem; color: #6b7a73; margin-top: 16px; }
        .error { background: #fdecea; color: #8a1f17; border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <main>
        @if ($state === 'open')
            <h1>Pay for your booking</h1>
            <div class="meta">Booking {{ $reference }}</div>
            @if ($tour)<div class="meta">{{ $tour }}@if ($departsOn) &middot; departing {{ $departsOn }}@endif</div>@endif
            <div class="amount">{{ $currency }} {{ number_format($amount, 2) }}</div>

            @if (session('pay_error'))<div class="error" role="alert">{{ session('pay_error') }}</div>@endif

            <form method="POST" action="{{ route('pay.start', $token) }}">
                @csrf
                <button type="submit">Pay securely</button>
            </form>
            <p class="note">You will be taken to our payment provider's secure page to enter your card details. LuxyBiz never sees or stores your card number.</p>
        @elseif ($state === 'paid')
            <h1>Already paid</h1>
            <p>This payment has been received. Thank you. You do not need to do anything else.</p>
        @elseif ($state === 'expired')
            <h1>This link has expired</h1>
            <p>For your security, payment links only work for a limited time. Please contact us and we will send you a new one.</p>
        @elseif ($state === 'cancelled')
            <h1>This link is no longer active</h1>
            <p>Please contact us if you still want to pay for your booking.</p>
        @else
            <h1>This link cannot be used</h1>
            <p>The link may be wrong, or the booking no longer needs a payment. Please contact us if you need help.</p>
        @endif
    </main>
</body>
</html>
