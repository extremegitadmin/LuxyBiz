{{-- PLACEHOLDER email for cancellation decisions (Day 19). Replace with Anakha's template when it is ready; keep the variables. --}}
<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1d2433;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;padding:32px;">
            <tr><td>
                <h2 style="margin:0 0 16px;font-size:20px;">{{ config('app.name') }}</h2>
                <p style="margin:0 0 12px;">Hello {{ $name }},</p>

                @if ($approved)
                    <p style="margin:0 0 16px;line-height:1.5;">Your request to cancel booking <strong>{{ $bookingReference }}</strong>@if ($tour) ({{ $tour }})@endif has been <strong>approved</strong>.</p>

                    @if ($refund > 0)
                        <p style="margin:0 0 8px;line-height:1.5;">Refund amount: <strong>{{ $currency }} {{ number_format($refund, 2) }}</strong></p>
                        <p style="margin:0 0 16px;line-height:1.5;">It will be returned to your original payment method within {{ $daysMin }} to {{ $daysMax }} working days.</p>
                        <p style="margin:0 0 16px;line-height:1.5;color:#44506b;">Prefer travel credit instead? Reply to this email and we will arrange it. Credit is valid for {{ $creditMonths }} months.</p>
                    @else
                        <p style="margin:0 0 16px;line-height:1.5;">Under the cancellation terms no refund is due for this booking.</p>
                    @endif
                @else
                    <p style="margin:0 0 16px;line-height:1.5;">We have reviewed your request to cancel booking <strong>{{ $bookingReference }}</strong>@if ($tour) ({{ $tour }})@endif. We are not able to approve it.</p>
                    @if ($note)
                        <p style="margin:0 0 16px;line-height:1.5;padding:12px;background:#f4f6f9;border-radius:6px;">{{ $note }}</p>
                    @endif
                    <p style="margin:0 0 16px;line-height:1.5;">Your booking stays as it was. If you have questions, reply to this email.</p>
                @endif

                <hr style="border:none;border-top:1px solid #e3e8ef;margin:20px 0;">
                <p style="margin:0;font-size:12px;color:#6b778c;">Request reference {{ $requestReference }}</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
