<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Violation notification' }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#223a4e;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f4f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #e6edf5;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#f8fbff;padding:24px;text-align:center;border-bottom:1px solid #e6edf5;">
                            <img src="{{ asset('images/brand/sitandgo-logo.png') }}" alt="Sit&Go" style="display:block;margin:0 auto;height:42px;max-width:100%;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 16px 28px;">
                            <h1 style="margin:0 0 10px 0;font-size:24px;line-height:1.2;color:#223a4e;">{{ $subject ?? 'Violation notification' }}</h1>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#38556f;">
                                Hello{{ ! empty($recipientName) ? ' ' . $recipientName : '' }},<br>
                                A traffic violation record has been created. Please review the details below.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 16px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;border:1px solid #e6edf5;font-size:13px;color:#607d96;width:42%;">Ticket number</td>
                                    <td style="padding:10px 12px;border:1px solid #e6edf5;font-size:14px;color:#223a4e;">{{ $ticketNumber ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;border:1px solid #e6edf5;font-size:13px;color:#607d96;">Violation type</td>
                                    <td style="padding:10px 12px;border:1px solid #e6edf5;font-size:14px;color:#223a4e;">{{ $violationType ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;border:1px solid #e6edf5;font-size:13px;color:#607d96;">Ticket date</td>
                                    <td style="padding:10px 12px;border:1px solid #e6edf5;font-size:14px;color:#223a4e;">{{ $ticketDate ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;border:1px solid #e6edf5;font-size:13px;color:#607d96;">Vehicle</td>
                                    <td style="padding:10px 12px;border:1px solid #e6edf5;font-size:14px;color:#223a4e;">{{ $vehicle ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;border:1px solid #e6edf5;font-size:13px;color:#607d96;">Issuing authority</td>
                                    <td style="padding:10px 12px;border:1px solid #e6edf5;font-size:14px;color:#223a4e;">{{ $issuingAuthority ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fbff;border:1px solid #e6edf5;font-size:13px;color:#607d96;">Total charge</td>
                                    <td style="padding:10px 12px;border:1px solid #e6edf5;font-size:14px;color:#223a4e;font-weight:bold;">{{ $totalCharge ?? '-' }} {{ $currency ?? '' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 24px 28px;">
                            <a href="{{ $detailsUrl ?? url('/admin/violations') }}" style="display:inline-block;padding:12px 18px;background:#2ecc71;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">
                                View in Sit&Go
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f8fbff;border-top:1px solid #e6edf5;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#7c93a9;">
                                This is an automated message from Sit&Go. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
