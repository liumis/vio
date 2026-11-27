<x-filament-panels::page>
    <style>
        .vio-v-tabs { display:inline-flex; gap:4px; padding:4px; border-radius:12px; background:#f1f5f9; border:1px solid #e2e8f0; margin-bottom:1rem; }
        .vio-v-tab { border:0; background:transparent; cursor:pointer; border-radius:9px; padding:8px 14px; font-size:13px; font-weight:650; color:#64748b; }
        .vio-v-tab.is-active { background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(15,23,42,.06), 0 0 0 1px rgba(226,232,240,.9); }
        .vio-acc-day { border-radius:16px; background:#fff; border:1px solid #cbd5e1; box-shadow:0 10px 28px rgba(34,58,78,.07); overflow:hidden; }
        .vio-acc-day + .vio-acc-day { margin-top:14px; }
        .vio-acc-day > .vio-acc-head { width:100%; display:flex; align-items:center; gap:12px; text-align:left; padding:16px 18px; background:#f8fafc; border:0; cursor:pointer; }
        .vio-acc-day > .vio-acc-head:hover { background:#e8eef5; }
        .vio-acc-day-body { padding:10px 12px 12px; background:#f8fafc; border-top:1px solid #e2e8f0; }
        .vio-acc { border-radius:14px; background:#fff; border:1px solid #e2e8f0; box-shadow:0 4px 14px rgba(34,58,78,.04); overflow:hidden; }
        .vio-acc + .vio-acc { margin-top:8px; }
        .vio-acc-head { width:100%; display:flex; align-items:center; gap:12px; text-align:left; padding:14px 16px; background:#f8fafc; border:0; cursor:pointer; }
        .vio-acc-head:hover { background:#f1f5f9; }
        .vio-acc-chevron { width:1.1rem; height:1.1rem; color:#64748b; flex-shrink:0; transition:transform .15s ease; }
        .vio-acc-open .vio-acc-chevron, .vio-acc-day.vio-acc-open > .vio-acc-head .vio-acc-chevron { transform:rotate(90deg); }
        .vio-acc-title { font-size:13px; font-weight:700; color:#0f172a; }
        .vio-acc-day .vio-acc-title { font-size:15px; font-weight:800; }
        .vio-acc-sub { font-size:11px; color:#64748b; margin-top:2px; }
        .vio-acc-count { margin-left:auto; font-size:11px; font-weight:700; color:#475569; background:#e2e8f0; border-radius:999px; padding:2px 9px; }
        .vio-acc-imported-when { flex-shrink:0; font-size:12px; font-weight:800; color:#047857; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .vio-acc-status { flex-shrink:0; font-size:11px; font-weight:700; border-radius:999px; padding:2px 9px; background:#ecfdf5; color:#047857; border:1px solid #86efac; }
        .vio-acc-body { border-top:1px solid #e2e8f0; }
        .vio-fines-table { border-collapse:separate; border-spacing:0; }
        .vio-fines-table thead th { position:sticky; top:0; z-index:1; background:#f8fafc; font-size:11px; letter-spacing:.04em; font-weight:600; color:#64748b; border-bottom:1px solid #e2e8f0; padding:10px 12px; white-space:nowrap; }
        .vio-fines-table tbody td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; font-size:13px; color:#0f172a; }
        .vio-fines-table tbody tr:hover td { background:#f8fafc; }
        .vio-chip { display:inline-flex; align-items:center; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:600; line-height:1.35; white-space:nowrap; }
        .vio-chip-ok { background:#ecfdf5; color:#047857; }
        .vio-chip-slate { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; }
        .vio-chip-warn { background:#fffbeb; color:#b45309; border:1px solid #fbbf24; }
        .vio-chip-sky { background:#f0f9ff; color:#0369a1; }
        .vio-chip-critical {
            background:#fef2f2; color:#b91c1c; border:1px solid #f87171;
            font-weight:800; letter-spacing:.01em;
            animation: vio-to-send-pulse 1.5s ease-in-out infinite;
        }
        @keyframes vio-to-send-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(185, 28, 28, .35); }
            50% { box-shadow: 0 0 0 5px rgba(185, 28, 28, 0); }
        }
        .vio-to-send-badge {
            flex-shrink:0; font-size:11px; font-weight:800; border-radius:999px;
            padding:2px 9px; line-height:1.35; white-space:nowrap;
            background:#fef2f2; color:#b91c1c; border:1px solid #f87171;
        }
        .vio-row-to-send td { background:#fff5f5 !important; }
        .vio-row-to-send:hover td { background:#fee2e2 !important; }
        .vio-row-not-imported td {
            background:#fff7ed !important;
            border-bottom-color:#fed7aa !important;
        }
        .vio-row-not-imported td:first-child {
            box-shadow: inset 4px 0 0 #ea580c;
        }
        .vio-row-not-imported:hover td { background:#ffedd5 !important; }
        .vio-row-imported td {
            background:#f8fafc !important;
            color:#64748b;
        }
        .vio-row-imported:hover td { background:#f1f5f9 !important; }
        .vio-row-imported .vio-chip-ok { opacity:.85; }
        .vio-chip-awaiting {
            background:#ffedd5; color:#9a3412; border:1px solid #fb923c;
            font-weight:800; letter-spacing:.01em;
        }
        .vio-acc-pending {
            border-color:#fdba74;
            box-shadow:0 4px 14px rgba(234,88,12,.08);
        }
        .vio-acc-pending > .vio-acc-head { background:#fff7ed; }
        .vio-acc-pending > .vio-acc-head:hover { background:#ffedd5; }
        .vio-not-imported-badge {
            flex-shrink:0; font-size:11px; font-weight:800; border-radius:999px;
            padding:2px 9px; line-height:1.35; white-space:nowrap;
            background:#ffedd5; color:#9a3412; border:1px solid #fb923c;
        }
        .vio-col-authority-email { min-width: 7.5rem; }
        .vio-footer-bar {
            z-index: 40 !important;
            background: rgba(255,255,255,.92) !important;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -12px 40px rgba(34,58,78,.1);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }
        [x-cloak] { display: none !important; }
    </style>

    {{-- Keep both layouts mounted: Filament table breaks if swapped in/out via @if. --}}
    <div x-data="{ mode: $wire.entangle('browseMode') }">
    <div class="vio-v-tabs" role="tablist" aria-label="Imported layout">
        <button
            type="button"
            class="vio-v-tab"
            :class="mode === 'days' && 'is-active'"
            @click="mode = 'days'"
        >By import day</button>
        <button
            type="button"
            class="vio-v-tab"
            :class="mode === 'table' && 'is-active'"
            @click="mode = 'table'"
        >Table</button>
    </div>

    <div x-show="mode === 'days'">
        @php $dayGroups = $this->violationDayGroups; @endphp
        @if ($dayGroups === [])
            <p class="text-sm text-gray-500">No imported fines yet.</p>
        @else
            @foreach ($dayGroups as $day)
                @php
                    $dayKey = (string) $day['key'];
                    $dayOpen = $this->isViolationGroupOpen($dayKey);
                @endphp
                <div wire:key="vday-{{ $dayKey }}" @class(['vio-acc-day', 'vio-acc-open' => $dayOpen, 'vio-acc-pending' => ($day['not_imported_count'] ?? 0) > 0])>
                    <button type="button" class="vio-acc-head" wire:click="toggleViolationGroup(@js($dayKey))">
                        <svg class="vio-acc-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                        </svg>
                        <div class="min-w-0">
                            <div class="vio-acc-title truncate">{{ $day['title'] }}</div>
                            <div class="vio-acc-sub truncate">{{ $day['subtitle'] }}</div>
                        </div>
                        @if (! empty($day['imported_at_label']))
                            <span class="vio-acc-imported-when">{{ $day['imported_at_label'] }}</span>
                        @endif
                        @if (($day['not_imported_count'] ?? 0) > 0)
                            <span class="vio-not-imported-badge" title="Still awaiting / missing in Wheelsys">
                                {{ $day['not_imported_count'] }} not imported
                            </span>
                        @endif
                        @if (($day['to_send_count'] ?? 0) > 0)
                            <span class="vio-to-send-badge" title="Authority not informed">
                                {{ $day['to_send_count'] }} not sent
                            </span>
                        @endif
                        @if (($day['not_imported_count'] ?? 0) === 0)
                            <span class="vio-acc-status">Imported</span>
                        @endif
                        <span class="vio-acc-count">{{ $day['fine_count'] }}</span>
                    </button>

                    @if ($dayOpen)
                        <div class="vio-acc-day-body">
                            @foreach ($day['files'] as $file)
                                @php
                                    $fileKey = (string) $file['key'];
                                    $fileOpen = $this->isViolationGroupOpen($fileKey);
                                    $logs = $file['logs'];
                                @endphp
                                <div wire:key="vfile-{{ $fileKey }}" @class(['vio-acc', 'vio-acc-open' => $fileOpen, 'vio-acc-pending' => ($file['not_imported_count'] ?? 0) > 0])>
                                    <button type="button" class="vio-acc-head" wire:click="toggleViolationGroup(@js($fileKey))">
                                        <svg class="vio-acc-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                        </svg>
                                        <div class="min-w-0">
                                            <div class="vio-acc-title truncate">{{ $file['title'] }}</div>
                                            <div class="vio-acc-sub truncate">{{ $file['subtitle'] }}</div>
                                        </div>
                                        @if (! empty($file['imported_at_label']) && ($file['not_imported_count'] ?? 0) === 0)
                                            <span class="vio-acc-imported-when">{{ $file['imported_at_label'] }}</span>
                                        @endif
                                        @if (($file['not_imported_count'] ?? 0) > 0)
                                            <span class="vio-not-imported-badge" title="Still awaiting / missing in Wheelsys">
                                                {{ $file['not_imported_count'] }} not imported
                                            </span>
                                        @endif
                                        @if (($file['to_send_count'] ?? 0) > 0)
                                            <span class="vio-to-send-badge" title="Authority not informed">
                                                {{ $file['to_send_count'] }} not sent
                                            </span>
                                        @endif
                                        <span class="vio-acc-count">{{ count($logs) }}</span>
                                    </button>

                                    @if ($fileOpen)
                                        <div class="vio-acc-body">
                                            @php
                                                $readyInFile = 0;
                                                foreach ($logs as $groupLog) {
                                                    if ($this->rowIsBulkSendable($groupLog)) {
                                                        $readyInFile++;
                                                    }
                                                }
                                            @endphp
                                            <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px 16px;border-bottom:1px solid #f1f5f9;background:#fff;">
                                                @if ($readyInFile > 0)
                                                    <button
                                                        type="button"
                                                        wire:click="selectReadyInFile(@js($fileKey))"
                                                        class="inline-flex items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm"
                                                        style="background:#16a34a;border:0;cursor:pointer;"
                                                    >
                                                        Select ready ({{ $readyInFile }})
                                                    </button>
                                                @endif
                                                <button
                                                    type="button"
                                                    wire:click="updateWheelsysStatus({{ \Illuminate\Support\Js::from($fileKey) }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="updateWheelsysStatus"
                                                    class="inline-flex items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-900 shadow-sm"
                                                    style="background:#fef3c7;border:1px solid #fbbf24;cursor:pointer;"
                                                >
                                                    <span wire:loading.remove wire:target="updateWheelsysStatus">Update status</span>
                                                    <span wire:loading wire:target="updateWheelsysStatus">Updating…</span>
                                                </button>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="vio-fines-table w-full min-w-[1080px] text-left">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:2.5rem;"></th>
                                                            <th>Reply</th>
                                                            <th>Preview / Send</th>
                                                            <th class="vio-col-authority-email">Authority email</th>
                                                            <th>Wheels</th>
                                                            <th>Imported</th>
                                                            <th>Vehicle</th>
                                                            <th>Driver</th>
                                                            <th>Reference</th>
                                                            <th>Contract</th>
                                                            <th>Authority</th>
                                                            <th>Informavimas</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($logs as $log)
                                                            @php
                                                                $reply = \App\Support\FineReplyReadiness::forLog($log);
                                                                $isEpaslaugos = ($reply['channel'] ?? '') === 'epaslaugos';
                                                                $needsAuthorityEmail = ! $isEpaslaugos && ! filled($log->email_sent_at);
                                                                $bulkSendable = $this->rowIsBulkSendable($log);
                                                                $isImported = $log->wheels_import_status === \App\Models\FineProcessLog::WHEELS_IMPORT_IMPORTED;
                                                                $docUrl = $log->documentSharePointUrl();
                                                                $rntPdfUrls = $log->rntContractPdfSharePointUrls();
                                                                $rntUrl = $rntPdfUrls[0] ?? null;
                                                                $needsContract = $log->authority?->requiresContract() === true
                                                                    || str_contains(mb_strtolower((string) ($log->authority_name ?? ''), 'UTF-8'), 'julianus');
                                                                $tvUrl = filled($log->wheels_violation_id)
                                                                    ? \App\Support\WheelsysLoginBridge::trafficViolationUrl((string) $log->wheels_violation_id)
                                                                    : null;
                                                            @endphp
                                                            <tr
                                                                wire:key="vrow-{{ $log->id }}"
                                                                @class([
                                                                    'vio-row-to-send' => $needsAuthorityEmail && $isImported,
                                                                    'vio-row-not-imported' => ! $isImported,
                                                                    'vio-row-imported' => $isImported && ! $needsAuthorityEmail,
                                                                ])
                                                            >
                                                                <td>
                                                                    <input
                                                                        type="checkbox"
                                                                        value="{{ $log->id }}"
                                                                        wire:model.live="selectedReplyIds"
                                                                        class="rounded border-slate-300"
                                                                        @disabled(! $bulkSendable)
                                                                        title="{{ $bulkSendable ? 'Select for bulk send' : 'Only Ready + Not sent can be bulk-sent' }}"
                                                                        style="{{ $bulkSendable ? '' : 'opacity:.35;cursor:not-allowed;' }}"
                                                                    />
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        $bg = $reply['sent'] ? '#e0f2fe' : ($reply['ready'] ? '#dcfce7' : '#fee2e2');
                                                                        $fg = $reply['sent'] ? '#0369a1' : ($reply['ready'] ? '#166534' : '#b91c1c');
                                                                        $dot = $reply['sent'] ? '#0ea5e9' : ($reply['ready'] ? '#22c55e' : '#ef4444');
                                                                    @endphp
                                                                    <span title="{{ $reply['detail'] }}" class="vio-chip" style="background:{{ $bg }};color:{{ $fg }};">
                                                                        <span style="width:8px;height:8px;border-radius:999px;background:{{ $dot }};margin-right:6px;display:inline-block;"></span>
                                                                        {{ $reply['label'] }}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        if ($reply['sent']) {
                                                                            $btnBg = '#e0f2fe'; $btnFg = '#0369a1'; $btnBd = '#7dd3fc'; $btnLabel = 'Sent';
                                                                        } elseif ($reply['ready']) {
                                                                            $btnBg = '#ecfdf5'; $btnFg = '#047857'; $btnBd = '#86efac'; $btnLabel = 'Preview / Send';
                                                                        } else {
                                                                            $btnBg = '#fff7ed'; $btnFg = '#c2410c'; $btnBd = '#fdba74'; $btnLabel = 'Preview / Send';
                                                                        }
                                                                    @endphp
                                                                    <button
                                                                        type="button"
                                                                        wire:click="openInformuoti({{ (int) $log->id }})"
                                                                        title="{{ $reply['detail'] }}"
                                                                        style="display:inline-flex;align-items:center;gap:8px;min-height:2.35rem;padding:6px 12px 6px 10px;border:1px solid {{ $btnBd }};border-radius:10px;background:{{ $btnBg }};color:{{ $btnFg }};cursor:pointer;font-size:12px;font-weight:800;line-height:1.2;white-space:nowrap;"
                                                                    >
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:1.35rem;height:1.35rem;flex-shrink:0;">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                                                        </svg>
                                                                        <span>{{ $btnLabel }}</span>
                                                                    </button>
                                                                </td>
                                                                <td class="vio-col-authority-email">
                                                                    @if ($isEpaslaugos)
                                                                        <span class="vio-chip vio-chip-slate" title="ANR / ePaslaugos — no authority email">Not needed</span>
                                                                    @elseif ($needsAuthorityEmail)
                                                                        <span class="vio-chip vio-chip-critical" title="Authority not informed yet">Not sent</span>
                                                                    @else
                                                                        <span class="vio-chip vio-chip-ok" title="Authority informed">Sent</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if ($isImported)
                                                                        <span class="vio-chip vio-chip-ok">Imported</span>
                                                                    @elseif ($log->wheels_import_status === \App\Models\FineProcessLog::WHEELS_IMPORT_MISSING)
                                                                        <span class="vio-chip vio-chip-warn" style="font-weight:800;">Missing in Wheels</span>
                                                                    @else
                                                                        <span class="vio-chip vio-chip-awaiting">Not imported</span>
                                                                    @endif
                                                                </td>
                                                                <td style="font-weight:800;color:{{ $isImported ? '#047857' : '#9a3412' }};font-variant-numeric:tabular-nums;white-space:nowrap;">
                                                                    {{ optional($log->wheels_imported_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i') ?? '—' }}
                                                                </td>
                                                                <td style="font-weight:700;">{{ $log->vehicle ?: '—' }}</td>
                                                                <td>{{ $log->driver_name ?: '—' }}</td>
                                                                <td>{{ $log->reference ?: '—' }}</td>
                                                                <td>
                                                                    @php
                                                                        $needsContract = $log->authority?->requiresContract() === true
                                                                            || str_contains(mb_strtolower((string) ($log->authority_name ?? ''), 'UTF-8'), 'julianus');
                                                                        $agr = trim((string) ($log->agr_no ?? ''));
                                                                    @endphp
                                                                    @if ($agr !== '')
                                                                        <span class="vio-chip vio-chip-ok" title="Rental contract">{{ $agr }}</span>
                                                                    @elseif ($needsContract)
                                                                        <span class="vio-chip vio-chip-critical" title="Julianus requires RNT">RNT missing</span>
                                                                    @else
                                                                        <span style="color:#cbd5e1;">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if ($log->authority_name)
                                                                        <span class="vio-chip vio-chip-ok">{{ $log->authority_name }}</span>
                                                                    @else
                                                                        <span style="color:#cbd5e1;">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @include('filament.partials.informavimo-badge', [
                                                                        'deadline' => \App\Support\InformavimoTerminas::forLog($log),
                                                                    ])
                                                                </td>
                                                                <td style="white-space:nowrap;">
                                                                    @if ($docUrl)
                                                                        <x-filament::button tag="a" size="xs" color="gray" :href="$docUrl" target="_blank" rel="noopener noreferrer">
                                                                            Doc
                                                                        </x-filament::button>
                                                                    @endif
                                                                    @if ($rntPdfUrls !== [])
                                                                        @foreach ($rntPdfUrls as $rntIdx => $rntPdfUrl)
                                                                            <x-filament::button
                                                                                tag="a"
                                                                                size="xs"
                                                                                color="warning"
                                                                                :href="$rntPdfUrl"
                                                                                target="_blank"
                                                                                rel="noopener noreferrer"
                                                                                :title="basename(urldecode((string) (parse_url($rntPdfUrl, PHP_URL_PATH) ?: 'contract.pdf')))"
                                                                            >
                                                                                {{ \App\Models\FineProcessLog::rntContractButtonLabel($rntPdfUrl, $rntIdx, count($rntPdfUrls)) }}
                                                                            </x-filament::button>
                                                                        @endforeach
                                                                    @endif
                                                                    @if (filled($log->rental_id) && $rntPdfUrls === [])
                                                                        <x-filament::button
                                                                            size="xs"
                                                                            color="warning"
                                                                            wire:click="fetchRntDocs({{ (int) $log->id }})"
                                                                            wire:loading.attr="disabled"
                                                                            title="{{ $needsContract ? 'Required for this authority' : 'Optional — save RNT PDFs to SharePoint' }}"
                                                                        >
                                                                            Fetch contract
                                                                        </x-filament::button>
                                                                    @elseif (filled($log->rental_id) && $rntPdfUrls !== [])
                                                                        <x-filament::button
                                                                            size="xs"
                                                                            color="gray"
                                                                            wire:click="fetchRntDocs({{ (int) $log->id }})"
                                                                            wire:loading.attr="disabled"
                                                                            title="Re-fetch RNT attachments from Wheelsys"
                                                                        >
                                                                            Re-fetch
                                                                        </x-filament::button>
                                                                    @endif
                                                                    @if ($tvUrl)
                                                                        <x-filament::button tag="a" size="xs" color="primary" :href="$tvUrl" target="_blank" rel="noopener noreferrer">
                                                                            Wheels
                                                                        </x-filament::button>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="vio-footer-bar fixed inset-x-0 bottom-0">
                <div class="flex max-w-full flex-wrap items-center gap-2 px-3 py-3 lg:px-4">
                    <p class="mr-auto text-xs text-slate-500">
                        Selected <strong class="text-slate-800">{{ count($selectedReplyIds) }}</strong>
                        <span class="text-slate-400">· bulk send only Reply Ready</span>
                    </p>
                    <x-filament::button size="sm" color="gray" wire:click="clearReplySelection">Clear</x-filament::button>
                    <x-filament::button
                        size="sm"
                        color="success"
                        wire:click="sendSelectedReadyReplies"
                        wire:loading.attr="disabled"
                        wire:target="sendSelectedReadyReplies"
                        wire:confirm="Send authority replies for selected Ready rows only?"
                    >
                        <span wire:loading.remove wire:target="sendSelectedReadyReplies">Send selected replies</span>
                        <span wire:loading wire:target="sendSelectedReadyReplies">Sending…</span>
                    </x-filament::button>
                </div>
            </div>
            <div style="height: 4.5rem;"></div>
        @endif
    </div>

    <div x-show="mode === 'table'" x-cloak wire:key="imported-filament-table">
        {{ $this->table }}
    </div>
    </div>
</x-filament-panels::page>
