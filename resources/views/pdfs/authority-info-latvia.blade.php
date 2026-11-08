<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28mm 22mm 24mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; line-height: 1.45; }
        .header { margin-bottom: 18px; }
        .company { font-size: 11pt; font-weight: 700; }
        .meta { font-size: 9.5pt; color: #333; margin-top: 4px; }
        .doc-no { margin: 22px 0 8px; font-size: 10.5pt; }
        .title { text-align: center; font-size: 13pt; font-weight: 700; margin: 18px 0 22px; text-transform: uppercase; }
        .body p { margin: 0 0 10px; text-align: justify; }
        .fields { margin: 16px 0 8px; }
        .fields table { width: 100%; border-collapse: collapse; }
        .fields td { padding: 4px 0; vertical-align: top; }
        .fields td.k { width: 42%; color: #333; }
        .fields td.v { font-weight: 700; }
        .note { margin-top: 18px; font-size: 9.5pt; color: #555; }
        .footer { margin-top: 36px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $companyName }}</div>
        <div class="meta">Registration No. {{ $companyCode }}</div>
        <div class="meta">{{ $companyAddress }}</div>
        <div class="meta">{{ $companyPhone }}, {{ $companyEmail }}</div>
    </div>

    <div class="doc-no">Document No. <strong>{{ $documentNumber }}</strong></div>
    <div class="meta">{{ $documentDate }} · Vilnius</div>

    <div class="title">Notice<br>Regarding the person who committed the traffic offence</div>

    <div class="body">
        <p>
            With reference to case / notification No. <strong>{{ $reference }}</strong>,
            we hereby provide information about the person who was using the vehicle
            registration number <strong>{{ $vehicle !== '' ? $vehicle : '—' }}</strong>
            @if ($ticketDate !== '')
                on <strong>{{ $ticketDate }}</strong>
            @endif
            .
        </p>
    </div>

    <div class="fields">
        <table>
            <tr><td class="k">Full name</td><td class="v">{{ $driver !== '' ? $driver : '—' }}</td></tr>
            <tr><td class="k">Date of birth / ID</td><td class="v">{{ $birthDate !== '' ? $birthDate : '—' }}</td></tr>
            <tr><td class="k">Email</td><td class="v">{{ $email !== '' ? $email : '—' }}</td></tr>
            <tr><td class="k">Address</td><td class="v">{{ $address !== '' ? $address : '—' }}</td></tr>
            <tr><td class="k">Phone</td><td class="v">{{ $phone !== '' ? $phone : '—' }}</td></tr>
            @if ($license !== '')
                <tr><td class="k">Driving licence No.</td><td class="v">{{ $license }}</td></tr>
            @endif
            @if ($rental !== '')
                <tr><td class="k">Rental agreement</td><td class="v">{{ $rental }}</td></tr>
            @endif
        </table>
    </div>

    <p class="note">
        According to internal procedure this document should be signed via Marksign before sending
        to Latvian authorities, together with the original fine notice, power of attorney and RNT.
    </p>

    <div class="footer">
        <p>Yours sincerely,</p>
        {{ $companyName }}<br>
        {{ $signerTitle }}
    </div>
</body>
</html>
