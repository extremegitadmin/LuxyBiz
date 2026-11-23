{{-- PLACEHOLDER email for the customer payment link (Day 29). Replace with Anakha's template when it is ready; keep the variables. --}}
<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1d2433;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;padding:32px;">
            <tr><td>
                <h2 style="margin:0 0 16px;font-size:20px;">{{ config('app.name') }}</h2>
                <p style="margin:0 0 12px;">Hello {{ $name }},</p>
                <p style="margin:0 0 16px;line-height:1.5;">Here is your secure link to pay for booking <strong>{{ $bookingReference }}</strong>@if ($tour) ({{ $tour }}@if ($departsOn), departing {{ $departsOn }}@endif)@endif.</p>
                <p style="margin:0 0 20px;line-height:1.5;">Amount to pay: <strong>{{ $currency }} {{ number_format($amount, 2) }}</strong></p>
                <p style="margin:0 0 20px;"><a href="{{ $url }}" style="display:inline-block;background:#1f6f54;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;">Pay securely</a></p>
                <p style="margin:0 0 16px;line-height:1.5;color:#44506b;">You will be taken to our payment provider's secure page. We never see your card number. This link works until {{ $expiresAt }} and is meant only for you: please do not forward it.</p>
                <p style="margin:0 0 16px;line-height:1.5;color:#44506b;">If the button does not work, copy this address into your browser:<br><span style="word-break:break-all;">{{ $url }}</span></p>
                <hr style="border:none;border-top:1px solid #e3e8ef;margin:20px 0;">
                <p style="margin:0;font-size:12px;color:#6b7690;">Did not expect this email? Ignore it, or reply and let us know.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
