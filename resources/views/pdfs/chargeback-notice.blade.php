<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 16mm 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; line-height: 1.4; }
        .company { font-weight: 700; font-size: 11pt; }
        .meta { font-size: 9pt; color: #333; margin-top: 2px; }
        .to { margin: 16px 0 10px; }
        .title { text-align: center; font-size: 12pt; font-weight: 700; text-transform: uppercase; margin: 18px 0 14px; }
        .date { margin-bottom: 12px; }
        p { margin: 0 0 8px; text-align: justify; }
        ul { margin: 6px 0 12px 18px; padding: 0; }
        li { margin-bottom: 4px; }
        .section-en { margin-top: 28px; padding-top: 16px; border-top: 1px solid #bbb; }
        .sign { margin-top: 28px; }
        .small { font-size: 8.5pt; color: #444; }
    </style>
</head>
<body>
    <div class="company">{{ $companyName }}</div>
    <div class="meta">Juridinio asmens kodas {{ $companyCode }}</div>
    <div class="meta">Buveinės adresas: {{ $companyAddress }}</div>
    <div class="meta">{{ $companyPhone }}, {{ $companyEmail }}</div>

    <div class="to">
        <strong>{{ $clientName }}</strong><br>
        Siunčiama: {{ $clientEmail }}
    </div>

    <div class="title">PRANEŠIMAS<br>Dėl prievolės susimokėti administracinę baudą</div>
    <div class="date">{{ $documentDate }} · Vilnius</div>

    <p>
        Šiuo pranešimu informuojame, kad Jūsų, <strong>{{ $clientName }}</strong>, nuomos laikotarpiu,
        sutarties Nr. <strong>{{ $rentalNo }}</strong>, buvo užfiksuotas eismo taisyklių pažeidimas.
        @if ($companyPaidFine)
            Kadangi įmonė {{ $companyName }} gavo sprendimą / pranešimą ir buvo priversta sumokėti baudą
            už Jūsų padarytą kelių eismo pažeidimą Nr. <strong>{{ $reference }}</strong>.
        @else
            Pažeidimo Nr. <strong>{{ $reference }}</strong>.
        @endif
    </p>

    @if ($fineAmount > 0)
        <p>
            Baudos suma yra <strong>{{ number_format($fineAmount, 2, ',', ' ') }} EUR</strong>.
            Prašome per 30 kalendorinių dienų nuo šio pranešimo gavimo dienos apmokėti šią sumą
            bankiniu pavedimu pagal pateiktą sąskaitą.
        </p>
    @endif

    <p>
        Jūsų skolos suma šio pranešimo parengimo dieną yra
        <strong>{{ number_format($totalAmount, 2, ',', ' ') }} EUR</strong>, kuri susideda iš:
    </p>
    <ul>
        @if ($adminFee > 0)
            <li>
                {{ number_format($adminFee, 2, ',', ' ') }} EUR – administracinio mokesčio suma
                @if ($adminFeeLetterDate)
                    (laiškas apie administracinį mokestį buvo siųstas {{ $adminFeeLetterDate }})
                @endif
                ;
            </li>
        @endif
        @if ($fineAmount > 0)
            <li>{{ number_format($fineAmount, 2, ',', ' ') }} EUR – baudos mokesčio suma.</li>
        @endif
    </ul>

    <p class="small">
        Atkreipiame dėmesį, kad skolos suma gali keistis dėl skaičiuojamų netesybų.
        Tikslią mokėtiną sumą galite sužinoti susisiekę el. paštu {{ $companyEmail }}.
    </p>
    <p class="small">
        Informuojame, kad nepadengus visos susidariusios skolos per 30 dienų nuo šio pranešimo gavimo dienos,
        neteisminis skolos išieškojimas bus perduotas skolų išieškojimo paslaugų teikėjui UAB Legal Balance
        (juridinio asmens kodas 302528679, Žalgirio g. 90, Vilnius, el. paštas: priminimas@legalbalance.lt, tel. +370 52664324).
    </p>

    <div class="sign">
        {{ $companyName }}<br>
        Direktorius<br>
        {{ $signerFullName }}
    </div>

    <div class="section-en">
        <div class="company">{{ $companyName }}</div>
        <div class="meta">Company code {{ $companyCode }}</div>
        <div class="meta">{{ $companyAddress }}</div>
        <div class="meta">{{ $companyPhone }}, {{ $companyEmail }}</div>

        <div class="to">
            <strong>{{ $clientName }}</strong><br>
            Email: {{ $clientEmail }}
        </div>

        <div class="title">NOTICE<br>Regarding the Obligation to Pay an Administrative Fee</div>
        <div class="date">{{ $documentDate }} · Vilnius</div>

        <p>
            We hereby inform you, <strong>{{ $clientName }}</strong>, that a traffic violation was recorded
            during your rental period, rental agreement No. <strong>{{ $rentalNo }}</strong>
            (Violation No. <strong>{{ $reference }}</strong>).
        </p>

        @if ($fineAmount > 0)
            <p>
                The amount of the fine is <strong>EUR {{ number_format($fineAmount, 2, '.', ',') }}</strong>.
                Please pay this amount by bank transfer within 30 calendar days from the date of receipt of this notice,
                according to the invoice provided.
            </p>
        @endif

        <p>
            As of the date of issuance of this notice, your total outstanding amount is
            <strong>EUR {{ number_format($totalAmount, 2, '.', ',') }}</strong>, consisting of:
        </p>
        <ul>
            @if ($adminFee > 0)
                <li>
                    EUR {{ number_format($adminFee, 2, '.', ',') }} – administrative fee
                    @if ($adminFeeLetterDate)
                        (a letter regarding the administrative fee was sent on {{ $adminFeeLetterDate }})
                    @endif
                    ;
                </li>
            @endif
            @if ($fineAmount > 0)
                <li>EUR {{ number_format($fineAmount, 2, '.', ',') }} – traffic fine.</li>
            @endif
        </ul>

        <p class="small">
            If the full amount is not settled within 30 days, the out-of-court debt recovery process may be transferred
            to UAB Legal Balance (company code 302528679).
        </p>

        <div class="sign">
            {{ $companyName }}<br>
            Director<br>
            {{ $signerFullName }}
        </div>
    </div>
</body>
</html>
