<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 26mm 22mm 24mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; line-height: 1.5; }
        .company { font-size: 12pt; font-weight: 700; }
        .meta { font-size: 9.5pt; color: #333; margin-top: 3px; }
        .title { text-align: center; font-size: 14pt; font-weight: 700; margin: 28px 0 18px; text-transform: uppercase; }
        p { margin: 0 0 12px; text-align: justify; }
        .footer { margin-top: 40px; }
    </style>
</head>
<body>
    <div class="company">{{ $companyName }}</div>
    <div class="meta">Registration No. {{ $companyCode }}</div>
    <div class="meta">{{ $companyAddress }}</div>
    <div class="meta">{{ $companyPhone }}, {{ $companyEmail }}</div>
    <div class="meta" style="margin-top: 10px;">{{ $documentDate }} · Vilnius</div>

    <div class="title">Power of attorney / Įgaliojimas</div>

    <p>
        {{ $companyName }}, company code {{ $companyCode }}, represented by
        <strong>{{ $signerTitle }} {{ $signerFullName }}</strong>, hereby authorises the employees of
        {{ $companyName }} (GreenLease / GreenMotion) to represent the company before
        <strong>{{ $authorityName }}</strong> ({{ $authorityEmail }}) in matters related to traffic
        offences committed with vehicles owned or operated by the company, including submission of
        information about the person who was using the vehicle at the time of the offence and related correspondence.
    </p>

    <p>
        This power of attorney is valid from <strong>{{ $validFrom }}</strong>
        until <strong>{{ $validUntil }}</strong> (inclusive), unless revoked earlier in writing.
    </p>

    <div class="footer">
        <p>Yours sincerely,</p>
        {{ $signerTitle }}<br>
        {{ $signerFullName }}<br>
        {{ $companyName }}
    </div>
</body>
</html>
