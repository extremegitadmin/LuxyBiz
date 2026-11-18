<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1d2433;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;padding:32px;">
            <tr><td>
                <h2 style="margin:0 0 16px;font-size:20px;">{{ config('app.name') }}</h2>
                <p style="margin:0 0 12px;">Hello {{ $name }},</p>
                <p style="margin:0 0 20px;line-height:1.5;">We received a request to reset the password for your account. Click the button below to choose a new password.</p>
                <p style="margin:0 0 24px;">
                    <a href="{{ $url }}" style="background:#2f5bea;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;display:inline-block;">Reset password</a>
                </p>
                <p style="margin:0 0 8px;font-size:13px;color:#6b778c;">This link expires in {{ $minutes }} minutes and can be used once.</p>
                <p style="margin:0 0 16px;font-size:13px;color:#6b778c;">If you did not ask for this, you can ignore this email. Your password will not change.</p>
                <hr style="border:none;border-top:1px solid #e3e8ef;margin:20px 0;">
                <p style="margin:0;font-size:12px;color:#6b778c;">If the button does not work, copy this link into your browser:<br>
                    <span style="word-break:break-all;">{{ $url }}</span></p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>