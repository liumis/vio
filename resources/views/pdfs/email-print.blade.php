<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111; line-height: 1.45; }
        .meta { border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 14px; }
        .meta table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td.k { width: 18%; color: #555; }
        .meta td.v { font-weight: 600; }
        .note { font-size: 8.5pt; color: #666; margin-bottom: 12px; }
        .body { font-size: 10.5pt; }
        .body img { max-width: 100%; height: auto; }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="note">Išsaugota iš el. laiško (Print to PDF) · {{ $printedAt }}</div>
    <div class="meta">
        <table>
            <tr><td class="k">From</td><td class="v">{{ $from }}</td></tr>
            <tr><td class="k">To</td><td class="v">{{ $to }}</td></tr>
            <tr><td class="k">Subject</td><td class="v">{{ $subject }}</td></tr>
            <tr><td class="k">Received</td><td class="v">{{ $receivedAt }}</td></tr>
        </table>
    </div>
    <div class="body">
        @if ($isHtml)
            {!! $bodyHtml !!}
        @else
            <pre>{{ $bodyText }}</pre>
        @endif
    </div>
</body>
</html>
