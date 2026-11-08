<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Traffic Violation in Wheelsys</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        h1 { margin: 0 0 8px; font-size: 1.35rem; }
        .muted { color: #64748b; font-size: .92rem; line-height: 1.45; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0 8px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 8px; padding: 10px 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #223a4e; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-size: .92rem; }
        th { color: #64748b; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
        code { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 6px; }
        .copy { background: #223a4e; color: #fff; border: 0; border-radius: 6px; padding: 6px 10px; cursor: pointer; font-size: .8rem; }
        .hint { color: #64748b; font-size: .8rem; margin-top: 2px; }
        .ok { color: #15803d; font-size: .85rem; margin-left: 6px; }
        .steps { margin: 0; padding-left: 18px; }
        .steps li { margin: 6px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Create in Wheelsys (safe mode)</h1>
        <p class="muted">
            VIO prepared the values below. Click <strong>Open Wheels New form</strong> — you will be signed in
            and landed on <code>Traffic Violation - New</code>. Fill the form using Copy on each field.
            Full auto-create comes later.
        </p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ $openUrl }}" target="_blank" rel="noopener noreferrer" id="open-wheels">
                Open Wheels New form
            </a>
            <a class="btn btn-secondary" href="{{ \App\Filament\Pages\WheelsysManualSearch::getUrl() }}">Back to VIO search</a>
        </div>

        <ol class="steps muted">
            <li>Open the Wheels form (new tab).</li>
            <li>Paste <strong>Vehicle</strong>, then <strong>Date</strong> and <strong>Time</strong> — Wheels should resolve RNT/Customer.</li>
            <li>Paste <strong>Reference no</strong> and choose <strong>Authority</strong>.</li>
            <li>Set Fine received date if needed, then Save in Wheels.</li>
        </ol>

        <table>
            <thead>
            <tr>
                <th>Field</th>
                <th>Value</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($fields as $field)
                <tr>
                    <td>
                        <strong>{{ $field['label'] }}</strong>
                        <div class="hint">{{ $field['hint'] }}</div>
                    </td>
                    <td>
                        @if ($field['value'] !== '')
                            <code id="val-{{ $loop->index }}">{{ $field['value'] }}</code>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($field['value'] !== '')
                            <button
                                type="button"
                                class="copy"
                                data-copy="{{ $field['value'] }}"
                                data-target="ok-{{ $loop->index }}"
                            >Copy</button>
                            <span class="ok" id="ok-{{ $loop->index }}" hidden>Copied</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<script>
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const value = btn.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(value);
            } catch (e) {
                const ta = document.createElement('textarea');
                ta.value = value;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            const ok = document.getElementById(btn.getAttribute('data-target'));
            if (ok) {
                ok.hidden = false;
                setTimeout(() => { ok.hidden = true; }, 1200);
            }
        });
    });
</script>
</body>
</html>
