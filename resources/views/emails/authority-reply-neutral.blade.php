<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111111;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#ffffff;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #dddddd;">
                    <tr>
                        <td style="padding:28px 28px 24px 28px;">
                            <h1 style="margin:0 0 16px 0;font-size:20px;line-height:1.3;font-weight:700;color:#111111;">{{ $subject }}</h1>
                            <div style="margin:0;font-size:15px;line-height:1.6;color:#111111;">
                                @php
                                    $escaped = e($contentText);
                                    $withBold = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
                                @endphp
                                {!! nl2br($withBold) !!}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
