<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; line-height: 1.35; }
        h1 { font-size: 16pt; margin: 0 0 4px; }
        h2 { font-size: 11.5pt; margin: 16px 0 8px; border-bottom: 1.5px solid #1e3a5f; padding-bottom: 3px; color: #1e3a5f; }
        h3 { font-size: 10pt; margin: 12px 0 6px; color: #334155; }
        .meta { font-size: 8.5pt; color: #64748b; margin-bottom: 12px; }
        .box {
            border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 10px; margin: 0 0 8px;
            background: #f8fafc;
        }
        .box.blue { background: #eff6ff; border-color: #93c5fd; }
        .box.amber { background: #fffbeb; border-color: #fcd34d; }
        .box.green { background: #f0fdf4; border-color: #86efac; }
        .box.rose { background: #fff1f2; border-color: #fda4af; }
        .box.gray { background: #f1f5f9; border-color: #94a3b8; }
        .title { font-weight: 700; margin-bottom: 4px; }
        .arrow { text-align: center; color: #64748b; font-size: 11pt; margin: 2px 0 6px; }
        table.flow { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        table.flow th, table.flow td {
            border: 1px solid #cbd5e1; padding: 5px 7px; vertical-align: top; font-size: 8.5pt;
        }
        table.flow th { background: #1e3a5f; color: #fff; text-align: left; }
        table.flow td.step { width: 18%; font-weight: 700; background: #f1f5f9; }
        ul { margin: 4px 0 4px 16px; padding: 0; }
        li { margin: 0 0 2px; }
        .note { font-size: 8pt; color: #475569; }
        .tag {
            display: inline-block; font-size: 7.5pt; font-weight: 700; padding: 1px 5px;
            border-radius: 3px; background: #e2e8f0; color: #334155;
        }
        .tag.auto { background: #dcfce7; color: #166534; }
        .tag.assist { background: #fef3c7; color: #92400e; }
        .tag.manual { background: #e2e8f0; color: #475569; }
        .tag.out { background: #fee2e2; color: #991b1b; }
        .cols { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 4px 0 8px; }
        .cols td { width: 50%; vertical-align: top; }
        .footer { margin-top: 18px; font-size: 8pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .mermaid-like {
            font-family: DejaVu Sans, monospace; font-size: 8pt; white-space: pre-wrap;
            background: #0f172a; color: #e2e8f0; padding: 10px 12px; border-radius: 4px; line-height: 1.4;
        }
    </style>
</head>
<body>
    <h1>VIO — baudų administravimo sistema</h1>
    <div class="meta">
        Procesų schema (dabartinė būsena) · {{ $generatedAt }} · GreenLease / BMS Technologija
    </div>

    <h2>1. Bendras vaizdas</h2>
    <div class="box blue">
        <div class="title">VIO = pagalbinis sluoksnis aplink Wheelsys + el. paštą + SharePoint</div>
        <ul>
            <li><strong>Įėjimas:</strong> el. paštas (Microsoft Graph) arba fizinis laiškas / teismo nutarimas (rankinė forma)</li>
            <li><strong>Apdorojimas:</strong> institucijos atpažinimas, vairuotojo paieška Wheelsys, dokumentų archyvavimas SharePoint</li>
            <li><strong>Išėjimas:</strong> atsakymas institucijai (el. paštas / ePaslaugos / MarkSign paketas) + Wheelsys Traffic violation importas</li>
            <li><strong>Po nuskaičiavimo:</strong> chargeback (MarkSign) + LT debt registracija; Finvalda — kitas etapas</li>
        </ul>
    </div>

    <div class="mermaid-like">┌──────────────┐     ┌─────────────┐     ┌──────────────┐     ┌─────────────────┐
│  El. paštas  │────▶│  New fines  │────▶│  Atsakymas / │────▶│ Wheelsys import │
│  Graph inbox │     │  pipeline   │     │  ePaslaugos  │     │  Excel + SSO    │
└──────────────┘     └─────────────┘     └──────────────┘     └────────┬────────┘
┌──────────────┐            │                                         │
│  New fine /  │────────────┘                                         ▼
│  Court form  │                                           ┌─────────────────┐
└──────────────┘                                           │ Imported fines  │
                                                           │ + chargeback    │
                                                           └────────┬────────┘
                                                                    ▼
                                                           ┌─────────────────┐
                                                           │ LT debt /       │
                                                           │ MarkSign        │
                                                           │ (be Finvaldos)  │
                                                           └─────────────────┘</div>

    <h2>2. Meniu (Fines + Settings)</h2>
    <table class="flow">
        <tr>
            <th>Puslapis</th>
            <th>Paskirtis</th>
        </tr>
        <tr><td class="step">New fines</td><td>Inbox skenavimas, Process pipeline, atsakymo draftas, ePaslaugos / LV paketas</td></tr>
        <tr><td class="step">New fine</td><td>Fiziniai laiškai (skenai) → SharePoint → Wheelsys eilė</td></tr>
        <tr><td class="step">Court decisions</td><td>Teismo nutarimai — manual checklist + archyvas</td></tr>
        <tr><td class="step">Wheelsys import</td><td>Excel generavimas, SharePoint, importo patikra</td></tr>
        <tr><td class="step">Open Wheelsys / Manual search</td><td>SSO / paieška Wheelsys</td></tr>
        <tr><td class="step">Imported fines</td><td>Importuoti TV: chargeback, LT debt, Lenkijos atsakymas</td></tr>
        <tr><td class="step">ePaslaugos Atmesti</td><td>Periodinis Atmestų sekimas po ROIK pateikimo</td></tr>
        <tr><td class="step">System settings</td><td>Graph, SharePoint, Wheelsys, MarkSign, LT debt URL, LV įgaliojimas (POA)</td></tr>
        <tr><td class="step">Authorities</td><td>Institucijos, šablonai, informavimo terminas, PDF tipas</td></tr>
    </table>

    <h2>3. Pagrindinis el. pašto srautas</h2>
    <table class="flow">
        <tr>
            <th>#</th>
            <th>Žingsnis</th>
            <th>Kas vyksta</th>
            <th>Tipas</th>
        </tr>
        <tr>
            <td>1</td><td class="step">Scan inbox</td>
            <td>Microsoft Graph skaito administration@…; filtruoja pagal Authority email_pattern</td>
            <td><span class="tag auto">AUTO</span></td>
        </tr>
        <tr>
            <td>2</td><td class="step">Process</td>
            <td>PDF OCR/tekstas → plate / data / reference → Wheelsys rental lookup → SharePoint upload → draftas</td>
            <td><span class="tag auto">AUTO</span></td>
        </tr>
        <tr>
            <td>3</td><td class="step">Peržiūra</td>
            <td>Operatorius tikrina draftą, Wheelsys eilutę, trūkstamus laukus; Informavimo terminas (5 d.d.)</td>
            <td><span class="tag assist">ASSIST</span></td>
        </tr>
        <tr>
            <td>4</td><td class="step">Institucijos šaka</td>
            <td>Pagal tipą: el. paštas / ePaslaugos / LV MarkSign paketas (žr. §4)</td>
            <td><span class="tag assist">ASSIST</span></td>
        </tr>
        <tr>
            <td>5</td><td class="step">Wheelsys import</td>
            <td>Generate Excel → importuoti Wheelsys → Update status (verify)</td>
            <td><span class="tag assist">ASSIST</span></td>
        </tr>
    </table>

    <h2>4. Institucijų šakos</h2>
    <table class="flow">
        <tr>
            <th>Institucija</th>
            <th>Atsakymo kanalas</th>
            <th>VIO elgesys</th>
        </tr>
        <tr>
            <td class="step">Vilnius ANAM</td>
            <td>El. paštas + info PDF</td>
            <td>Generuoja Vilniaus PDF (ref. vardu), prisega gautą pranešimą, siunčia</td>
        </tr>
        <tr>
            <td class="step">ANR / ROIK</td>
            <td>IRD ePaslaugos (ne el. paštas)</td>
            <td>Assist: tipas, Copy laukai, portalas; „Mark done“ → Atmesti eilė</td>
        </tr>
        <tr>
            <td class="step">Julianus / Legal Balance / JUDU / Unipark / Parkavimas</td>
            <td>El. paštas</td>
            <td>Draftas; JUDU tipo — Print email→PDF į SharePoint</td>
        </tr>
        <tr>
            <td class="step">Latvija CPP</td>
            <td>El. paštas į kanceleja.cpp@vp.gov.lv</td>
            <td>LV PDF → MarkSign → paketas (pasirašytas raštas + originalas + POA + RNT); PRIMINIMAS → E-CSDD assist</td>
        </tr>
        <tr>
            <td class="step">Lenkija CAN</td>
            <td>canard@gitd.gov.pl</td>
            <td>New fine / Imported: Subject = CAN… + valst. nr.; siuntimas su priedu</td>
        </tr>
        <tr>
            <td class="step">Teismas</td>
            <td>Manual forma</td>
            <td>Court decisions: teisininkas, padalinys, checklist; be portalo automatizacijos</td>
        </tr>
        <tr>
            <td class="step">Kitos šalys (SE, EPC…)</td>
            <td>Manual</td>
            <td>Nedengiama automatika — rankinis procesas</td>
        </tr>
    </table>

    <h2>5. Fiziniai laiškai ir teismas</h2>
    <table class="cols">
        <tr>
            <td>
                <div class="box amber">
                    <div class="title">New fine</div>
                    <ul>
                        <li>Plate, reference, datos, vairuotojas, authority</li>
                        <li>Multi-failų upload → SharePoint</li>
                        <li>CAN… auto parenka Lenkiją</li>
                        <li>Eilė → Wheelsys import</li>
                    </ul>
                </div>
            </td>
            <td>
                <div class="box rose">
                    <div class="title">Court decisions</div>
                    <ul>
                        <li>Persiųsti teisininkui</li>
                        <li>Laukti: mokėti / ginčas</li>
                        <li>Padalinys LT/LV/EE</li>
                        <li>Bauda + adm. mokestis</li>
                        <li>Archyvas + Wheelsys eilė</li>
                    </ul>
                </div>
            </td>
        </tr>
    </table>

    <h2>6. Wheelsys importas</h2>
    <div class="box green">
        <ol>
            <li><strong>Wheelsys import</strong> — sugeneruoti Excel (Traffic violation šablonas)</li>
            <li>Failas SharePoint + lokalus; SSO / Open Wheelsys</li>
            <li>Importuoti Wheelsys UI</li>
            <li><strong>Update Wheelsys status</strong> — VIO patikrina, ar TV atsirado</li>
            <li>Rezultatas matomas <strong>Imported fines</strong></li>
        </ol>
        <p class="note" style="margin:6px 0 0;">
            Pastaba: VIO kelia PDF į SharePoint. Automatinio įkėlimo į Wheelsys „Traffic violation“ ir „Rental (RNT)“ priedų laukus nėra —
            tai lieka rankiniu Wheelsys žingsniu, jei reikia.
        </p>
    </div>

    <h2>7. Nuskaičiavimas ir chargeback (iki Finvaldos)</h2>
    <div class="box">
        <div class="title">Sėkmingas nuskaičiavimas (kortelė / depozitas)</div>
        <p class="note">Vykdoma Wheelsys + terminale (ne VIO automatika). Po to: Paid date, laiškas klientui (Priedas Nr.1) — šiuo metu daugiausia rankinis Wheelsys procesas.</p>
    </div>
    <div class="arrow">▼ jei nuskaičiuoti nepavyko</div>
    <table class="flow">
        <tr>
            <th>#</th>
            <th>Žingsnis</th>
            <th>VIO</th>
        </tr>
        <tr>
            <td>1</td><td class="step">Nepavyko nuskaičiuoti</td>
            <td>Imported fines → dvikalbis PDF (Priedas Nr.3) → MarkSign (Mykolas)</td>
        </tr>
        <tr>
            <td>2</td><td class="step">Parašas</td>
            <td>MarkSign callback → statusas Signed + pasirašytas PDF</td>
        </tr>
        <tr>
            <td>3</td><td class="step">Finvalda SF</td>
            <td><span class="tag out">KITAS ETAPAS</span> — šioje schemoje nedengiama</td>
        </tr>
        <tr>
            <td>4</td><td class="step">Registruoti LT debt</td>
            <td>CHARGEBACKS/DEBTS (SharePoint darbinė kopija) → lapas „LT debt“</td>
        </tr>
    </table>

    <h2>8. Kas dengiama / kas ne</h2>
    <table class="flow">
        <tr>
            <th>Būsena</th>
            <th>Turinys</th>
        </tr>
        <tr>
            <td><span class="tag auto">DENGIAMA</span></td>
            <td>Inbox, authority match, terminas, PDF (Vilnius/LV), atsakymai, ePaslaugos assist + Atmesti, LV MarkSign paketas, POA, Lenkija CAN, teismo manual forma, Wheelsys Excel/SSO/verify, chargeback MarkSign, LT debt SharePoint</td>
        </tr>
        <tr>
            <td><span class="tag manual">MANUAL</span></td>
            <td>IRD/E-CSDD portalų submit (be API), RNT priedai Wheelsys viduje, kitos šalys (SE/EPC…), sėkmingo nuskaičiavimo terminalas</td>
        </tr>
        <tr>
            <td><span class="tag out">NE / VĖLIAU</span></td>
            <td>Finvalda sąskaitos, juridinio mokesčio atskiras srautas, skolų Legal Balance etapas</td>
        </tr>
    </table>

    <h2>9. Duomenų srautai (santrauka)</h2>
    <div class="box gray">
        <ul>
            <li><strong>Microsoft Graph</strong> — inbox skaitymas, laiškų siuntimas, SharePoint failai</li>
            <li><strong>Wheelsys</strong> — rental lookup, SSO, Excel import (Traffic violation)</li>
            <li><strong>MarkSign</strong> — LV raštai, metinis įgaliojimas, chargeback pranešimai</li>
            <li><strong>SharePoint CHARGEBACKS-DEBTS.xlsx</strong> — LT debt eilutės po pranešimo klientui</li>
            <li><strong>fine_process_logs</strong> — visų baudų būsenos, workflow žingsniai, MarkSign UUID</li>
        </ul>
    </div>

    <div class="footer">
        Sugeneruota iš VIO kodo būsenos · dokumentas informacinis (schema), ne teisinė instrukcija.
        Šaltinis: Policijos administravimo instrukcija + dabartinė VIO implementacija.
    </div>
</body>
</html>
