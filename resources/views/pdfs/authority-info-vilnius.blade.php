<!DOCTYPE html>
<html lang="lt">
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
        .footer { margin-top: 36px; }
        .sign { margin-top: 28px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $companyName }}</div>
        <div class="meta">Juridinio asmens kodas {{ $companyCode }}</div>
        <div class="meta">{{ $companyAddress }}</div>
        <div class="meta">{{ $companyPhone }}, {{ $companyEmail }}</div>
    </div>

    <div class="doc-no">Dokumento Nr. <strong>{{ $documentNumber }}</strong></div>
    <div class="meta">{{ $documentDate }} · Vilnius</div>

    <div class="title">Pranešimas<br>Dėl informacijos apie asmenį, padariusį pažeidimą</div>

    <div class="body">
        <p>
            Pagal gautą Vilniaus miesto savivaldybės administracijos viešosios tvarkos grupės
            pranešimą Nr. <strong>{{ $reference }}</strong>, pateikiame informaciją apie asmenį,
            @if ($driver !== '')
                <strong>{{ $driver }}</strong>,
            @endif
            @if ($ticketDate !== '')
                pažeidimo data <strong>{{ $ticketDate }}</strong>,
            @endif
            padariusį pažeidimą transporto priemone valstybinis numeris
            <strong>{{ $vehicle !== '' ? $vehicle : '—' }}</strong>.
        </p>
    </div>

    <div class="fields">
        <table>
            <tr><td class="k">Vardas, pavardė</td><td class="v">{{ $driver !== '' ? $driver : '—' }}</td></tr>
            <tr><td class="k">Gimimo data arba asmens kodas</td><td class="v">{{ $birthDate !== '' ? $birthDate : '—' }}</td></tr>
            <tr><td class="k">El. paštas</td><td class="v">{{ $email !== '' ? $email : '—' }}</td></tr>
            <tr><td class="k">Adresas</td><td class="v">{{ $address !== '' ? $address : '—' }}</td></tr>
            <tr><td class="k">Telefonas</td><td class="v">{{ $phone !== '' ? $phone : '—' }}</td></tr>
            @if ($license !== '')
                <tr><td class="k">Vair. pažymėjimo numeris</td><td class="v">{{ $license }}</td></tr>
            @endif
            @if ($rental !== '')
                <tr><td class="k">Nuomos sutartis</td><td class="v">{{ $rental }}</td></tr>
            @endif
        </table>
    </div>

    <div class="footer">
        <p>Pagarbiai,</p>
        <div class="sign">
            {{ $companyName }}<br>
            {{ $signerTitle }}
        </div>
    </div>
</body>
</html>
