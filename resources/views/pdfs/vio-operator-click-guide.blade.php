<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 12mm 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #0f172a; line-height: 1.35; }
        h1 { font-size: 15pt; margin: 0 0 2px; color: #0f172a; }
        h2 { font-size: 11pt; margin: 14px 0 6px; color: #1e3a5f; border-bottom: 1.5px solid #1e3a5f; padding-bottom: 3px; }
        h3 { font-size: 9.5pt; margin: 10px 0 5px; color: #334155; }
        .meta { font-size: 8pt; color: #64748b; margin-bottom: 10px; }
        .lead { background: #eff6ff; border: 1px solid #93c5fd; border-radius: 4px; padding: 8px 10px; margin: 0 0 10px; }
        .warn { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 4px; padding: 8px 10px; margin: 6px 0 10px; }
        .ok { background: #f0fdf4; border: 1px solid #86efac; border-radius: 4px; padding: 8px 10px; margin: 6px 0 10px; }
        .rose { background: #fff1f2; border: 1px solid #fda4af; border-radius: 4px; padding: 8px 10px; margin: 6px 0 10px; }
        .title { font-weight: 700; margin-bottom: 3px; }
        ul, ol { margin: 3px 0 3px 16px; padding: 0; }
        li { margin: 0 0 2px; }
        .note { font-size: 7.5pt; color: #64748b; }
        .pin {
            display: inline-block; width: 14px; height: 14px; line-height: 14px; text-align: center;
            border-radius: 50%; background: #dc2626; color: #fff; font-size: 7.5pt; font-weight: 700;
            margin-right: 3px; vertical-align: middle;
        }
        .pin.b { background: #2563eb; }
        .pin.g { background: #059669; }
        .pin.a { background: #d97706; }
        .pin.p { background: #7c3aed; }

        /* Mock Filament screen */
        .screen {
            border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin: 6px 0 8px;
            page-break-inside: avoid;
        }
        .screen-bar {
            background: #1e293b; color: #e2e8f0; padding: 5px 8px; font-size: 7.5pt;
        }
        .screen-nav {
            background: #0f172a; color: #94a3b8; padding: 4px 8px; font-size: 7pt;
        }
        .screen-nav strong { color: #fff; }
        .screen-body { background: #f8fafc; padding: 8px; }
        .toolbar {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px;
            margin-bottom: 6px;
        }
        .btn {
            display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 7.5pt;
            font-weight: 700; margin: 1px 2px 1px 0; border: 1px solid #cbd5e1; background: #f1f5f9; color: #334155;
        }
        .btn.primary { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .btn.warn { background: #d97706; border-color: #b45309; color: #fff; }
        .btn.info { background: #0284c7; border-color: #0369a1; color: #fff; }
        .btn.hit {
            box-shadow: 0 0 0 2px #fecaca; outline: 1px solid #dc2626;
        }
        .tbl { width: 100%; border-collapse: collapse; background: #fff; font-size: 7.5pt; }
        .tbl th { background: #f1f5f9; text-align: left; padding: 4px 5px; border-bottom: 1px solid #e2e8f0; color: #64748b; }
        .tbl td { padding: 4px 5px; border-bottom: 1px solid #f1f5f9; }
        .modal {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;
        }
        .modal-h { padding: 8px 10px; border-bottom: 1px solid #eef2f7; }
        .modal-b { padding: 8px 10px; }
        .modal-f { padding: 8px 10px; border-top: 1px solid #eef2f7; text-align: right; background: #fafafa; }
        .assist {
            background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; padding: 7px 8px; margin: 6px 0;
        }
        .cols { width: 100%; border-collapse: separate; border-spacing: 6px 0; }
        .cols td { width: 50%; vertical-align: top; }
        .legend td { padding: 2px 4px; font-size: 8pt; vertical-align: top; }
        .footer { margin-top: 12px; font-size: 7.5pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
        .strike { text-decoration: line-through; color: #94a3b8; }
        .chip { display: inline-block; font-size: 7pt; font-weight: 700; padding: 1px 5px; border-radius: 8px; background: #e2e8f0; }
        .chip.ok { background: #dcfce7; color: #166534; }
        .chip.warn { background: #fef3c7; color: #92400e; }
        .chip.no { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>VIO — operatoriaus pagrindinis flow</h1>
    <div class="meta">
        Kur spausti · nuo New fines iki Wheelsys Excel ir institucijos atsakymo · {{ $generatedAt }}
    </div>

    <div class="lead">
        <div class="title">Tikslas</div>
        <ol>
            <li>Surasti vairuotoją ir paruošti atsakymą institucijai</li>
            <li>Atsakyti teisingu kanalu (el. paštas <strong>arba</strong> išorinė valstybės sistema)</li>
            <li>Suvesti baudą į Wheelsys per Excel importą</li>
        </ol>
    </div>

    <div class="warn">
        <div class="title">Svarbu — el. paštas ne visada</div>
        Kai bauda eina per <strong>išorinę valstybinę sistemą</strong> (pvz. ANR / ROIK → IRD ePaslaugos),
        VIO <strong>nerodo „Send reply“</strong>. Vietoj to — instrukcija + copy laukai + „Mark … done“.
        Nesiųskite į no-reply adresus.
    </div>

    <h2>1. New fines — skenavimas ir vairuotojas</h2>
    <div class="screen">
        <div class="screen-bar">VIO · Fines → New fines</div>
        <div class="screen-nav">Meniu: <strong>New fines</strong> · New fine · Court decisions · Wheelsys import · Imported fines</div>
        <div class="screen-body">
            <div class="toolbar">
                <span class="btn hit"><span class="pin">1</span>Scan inbox / Fetch</span>
                <span class="btn"><span class="pin b">2</span>Find driver (pasirinkus eilutes)</span>
                <span class="btn">Filter by authority</span>
            </div>
            <table class="tbl">
                <tr><th></th><th>Received</th><th>Vehicle</th><th>Authority</th><th>Driver</th><th></th></tr>
                <tr>
                    <td>☐</td><td>2026-11-08</td><td>ABC123</td><td>Vilnius ANAM</td><td><span class="chip ok">found</span></td>
                    <td><span class="btn hit"><span class="pin g">3</span>Details</span></td>
                </tr>
                <tr>
                    <td>☐</td><td>2026-11-07</td><td>XYZ987</td><td>ANR / ROIK</td><td><span class="chip warn">found</span></td>
                    <td><span class="btn">Details</span></td>
                </tr>
            </table>
        </div>
    </div>
    <table class="legend">
        <tr><td><span class="pin">1</span></td><td>Nuskaito inbox (Graph) — naujos baudos į lentelę</td></tr>
        <tr><td><span class="pin b">2</span></td><td>Pažymėtoms eilutėms ieško nuomos / vairuotojo Wheelsys</td></tr>
        <tr><td><span class="pin g">3</span></td><td>Atidaro Details — draftas + atsakymo kanalas</td></tr>
    </table>

    <h2>2A. Details — el. pašto institucijos (Vilnius, JUDU, Julianus…)</h2>
    <div class="ok">
        Čia rodomas mygtukas <strong>Send reply</strong> (arba <strong>Send reply + PDF</strong>, jei institucija turi info PDF šabloną).
    </div>
    <div class="screen">
        <div class="screen-bar">Details · Vilnius ANAM · PDF: VIL-….pdf</div>
        <div class="screen-body">
            <div class="modal">
                <div class="modal-h">
                    <strong>Details</strong><br>
                    <span class="note">Vilnius ANAM · PDF: 2026-…-ABC123.pdf</span>
                </div>
                <div class="modal-b">
                    <div class="note">To: …@vilnius.lt · Subject: … · body draftas redaguojamas</div>
                    <div style="height: 28px; background: #f1f5f9; border: 1px dashed #cbd5e1; margin-top: 6px; text-align: center; color: #94a3b8; font-size: 7.5pt; line-height: 28px;">
                        Laiško drafto peržiūra
                    </div>
                </div>
                <div class="modal-f">
                    <span class="btn">Close</span>
                    <span class="btn">Create in Wheels</span>
                    <span class="btn hit primary"><span class="pin">4</span>Send reply + PDF</span>
                </div>
            </div>
        </div>
    </div>
    <p class="note"><span class="pin">4</span> Išsiunčia atsakymą Graph’u + prisega info PDF (jei nustatytas). Po to bauda eina į Wheelsys importo eilę.</p>

    <h2>2B. Details — ANR / ROIK (ePaslaugos) · BE el. pašto</h2>
    <div class="rose">
        <div class="title">Nėra „Send reply“</div>
        UI antraštė: <em>Details · ANR / ePaslaugos</em>. Instrukcija: duomenis teikti per IRD ePaslaugas, ne į auto-anr-no-reply.
    </div>
    <div class="screen">
        <div class="screen-bar">Details · ANR / ePaslaugos</div>
        <div class="screen-body">
            <div class="modal">
                <div class="modal-h">
                    <strong>Details · ANR / ePaslaugos</strong><br>
                    <span style="color:#b45309; font-size:7.5pt;">
                        ANR/ROIK: ne siųsti el. laišku. Duomenis teikti per IRD ePaslaugas (assisted žemiau).
                    </span>
                </div>
                <div class="modal-b">
                    <div class="assist">
                        <div class="title" style="font-size:7.5pt; color:#92400e;">ePaslaugos assist · ROIK pildymas</div>
                        <span class="btn hit warn"><span class="pin a">5</span>Open ePaslaugos</span>
                        <span class="btn hit"><span class="pin a">6</span>Copy ROIK</span>
                        <span class="btn hit"><span class="pin a">7</span>Copy plate</span>
                        <span class="btn hit"><span class="pin a">8</span>Copy driver block</span>
                        <div class="note" style="margin-top:5px;">Asmens tipas: LT fizinis / LT juridinis / užsienio…</div>
                    </div>
                    <div class="note">To laukas: <em>— ePaslaugos (ne el. paštas) —</em> (nesiunčiama)</div>
                </div>
                <div class="modal-f">
                    <span class="btn">Close</span>
                    <span class="btn strike">Send reply</span>
                    <span class="chip no">paslėpta</span>
                    <span class="btn hit warn"><span class="pin a">9</span>Mark ePaslaugos done</span>
                </div>
            </div>
        </div>
    </div>
    <table class="legend">
        <tr><td><span class="pin a">5</span></td><td>Atidaro <strong>https://epaslaugos.ird.lt/</strong> — pildykite „Transporto priemonės naudotojo teikimas“</td></tr>
        <tr><td><span class="pin a">6–8</span></td><td>Copy mygtukai — įklijuokite į portalo laukus</td></tr>
        <tr><td><span class="pin a">9</span></td><td>Kai portale pateikta — pažymėkite done → eilė <strong>ePaslaugos Atmesti</strong> (periodinis tikrinimas)</td></tr>
    </table>

    <h2>2C. Details — Latvija (MarkSign paketas) · ne paprastas Send</h2>
    <div class="warn">
        Vietoj „Send reply“: <strong>MarkSign LV raštą</strong> → po parašo <strong>Siųsti paketą į kanceleja</strong>
        (pasirašytas raštas + originalas + POA + RNT).
    </div>
    <div class="screen">
        <div class="screen-bar">Details · Latvija CPP · PDF (+ Marksign)</div>
        <div class="screen-body">
            <div class="modal-f" style="text-align:left; border:1px solid #e2e8f0; border-radius:4px;">
                <span class="btn">Close</span>
                <span class="btn strike">Send reply</span>
                <span class="chip no">paslėpta</span>
                <span class="btn hit info"><span class="pin p">10</span>MarkSign LV raštą</span>
                <span class="btn hit primary"><span class="pin p">11</span>Siųsti paketą į kanceleja</span>
            </div>
        </div>
    </div>

    <h2>3. Wheelsys import — Excel failas</h2>
    <div class="lead">
        Meniu: <strong>Wheelsys import</strong>. Tik baudos, kurioms jau surastas vairuotojas / paruošta eilė.
    </div>
    <div class="screen">
        <div class="screen-bar">VIO · Fines → Wheelsys import</div>
        <div class="screen-body">
            <div style="background:#1a2d3d; color:#e8eef3; padding:6px 8px; border-radius:4px; margin-bottom:6px; font-size:7.5pt;">
                Select ready fines → <strong>Generate import file</strong> → <strong>Start import</strong> in Wheels → <strong>Update Wheelsys status</strong>
            </div>
            <table class="tbl">
                <tr><th>☐</th><th>Vehicle</th><th>Driver</th><th>Reference</th><th>Status</th></tr>
                <tr><td>☑</td><td>ABC123</td><td>Jonas …</td><td>VIL-…</td><td><span class="chip ok">Ready</span></td></tr>
                <tr><td>☑</td><td>XYZ987</td><td>Petras …</td><td>ROIK-…</td><td><span class="chip ok">Ready</span></td></tr>
            </table>
            <div class="toolbar" style="margin-top:6px; text-align:right;">
                <span class="btn hit primary"><span class="pin">12</span>Generate import file</span>
                <span class="btn hit primary"><span class="pin">13</span>Start import</span>
                <span class="btn hit warn"><span class="pin">14</span>Update Wheelsys status</span>
            </div>
        </div>
    </div>
    <table class="legend">
        <tr><td><span class="pin">12</span></td><td>Sugeneruoja Traffic violation Excel + SharePoint; statusas → <em>In import file</em></td></tr>
        <tr><td><span class="pin">13</span></td><td>Atidaro failo pasirinkimą → Import Wheelsys UI (SSO)</td></tr>
        <tr><td><span class="pin">14</span></td><td>VIO patikrina, ar TV atsirado Wheelsys → matosi <strong>Imported fines</strong></td></tr>
    </table>

    <h2>4. Greita šakų lentelė</h2>
    <table class="tbl" style="font-size:8pt;">
        <tr>
            <th>Institucija</th>
            <th>Kas rodoma Details apačioje</th>
            <th>Ko nedaryti</th>
        </tr>
        <tr>
            <td>Vilnius / JUDU / Julianus / Unipark / …</td>
            <td><span class="chip ok">Send reply (+ PDF)</span></td>
            <td>—</td>
        </tr>
        <tr>
            <td>ANR / ROIK</td>
            <td><span class="chip warn">ePaslaugos assist + Mark ePaslaugos done</span></td>
            <td>Nesiųsti el. laišku į no-reply</td>
        </tr>
        <tr>
            <td>Latvija CPP</td>
            <td><span class="chip warn">MarkSign LV + Siųsti paketą</span></td>
            <td>Nenaudoti paprasto Send reply</td>
        </tr>
        <tr>
            <td>Lenkija CAN</td>
            <td>New fine / Imported: el. paštas į canard@gitd.gov.pl</td>
            <td>—</td>
        </tr>
        <tr>
            <td>Teismas</td>
            <td>Court decisions (checklist)</td>
            <td>Nėra automatinio portalo</td>
        </tr>
    </table>

    <div class="ok" style="margin-top:10px;">
        <div class="title">Minimalus dienos kelias</div>
        New fines → Fetch → Find driver → Details →
        <em>(Send / ePaslaugos / LV paketas)</em> →
        Wheelsys import → Generate → Start import → Update status.
    </div>

    <div class="footer">
        VIO operatoriaus click-guide · mock UI (ne live screenshot) · GreenLease / BMS · {{ $generatedAt }}
    </div>
</body>
</html>
