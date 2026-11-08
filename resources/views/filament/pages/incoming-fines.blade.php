<x-filament-panels::page>
    {{-- Teleport out of Filament stacking context so table/sticky cannot paint over it --}}
    <div wire:ignore.self x-data>
        <template x-teleport="body">
            <div
                wire:loading.flex
                wire:target="findRentalsSelected, openDetails, searchWheelsys"
                class="vio-busy-overlay"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="vio-busy-card">
                    <div class="vio-busy-spinner"></div>
                    <p class="vio-busy-title">Looking up rentals…</p>
                    <p class="vio-busy-sub">Ieškom — palaukite, nieko nespauskite.</p>
                </div>
            </div>
        </template>
    </div>
    <style>
        @keyframes vio-spin { to { transform: rotate(360deg); } }
        .vio-busy-overlay {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2147483000 !important;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.62) !important;
            -webkit-backdrop-filter: blur(2px);
            backdrop-filter: blur(2px);
            pointer-events: all !important;
        }
        .vio-busy-card {
            position: relative;
            z-index: 1;
            width: min(22rem, calc(100vw - 2rem));
            margin: 0 1rem;
            padding: 28px 24px;
            border-radius: 14px;
            background: #ffffff !important;
            color: #0f172a;
            text-align: center;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
            border: 1px solid #e2e8f0;
            isolation: isolate;
        }
        .vio-busy-spinner {
            width: 36px;
            height: 36px;
            margin: 0 auto;
            border-radius: 999px;
            border: 2px solid #e2e8f0;
            border-top-color: #0f172a;
            animation: vio-spin 0.8s linear infinite;
        }
        .vio-busy-title {
            margin: 16px 0 0;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .vio-busy-sub {
            margin: 6px 0 0;
            font-size: 12px;
            color: #64748b;
        }
        .vio-fines-table { border-collapse: separate; border-spacing: 0; position: relative; z-index: 0; }
        .vio-fines-table thead th {
            position: sticky; top: 0; z-index: 1;
            background: #f8fafc; font-size: 11px; letter-spacing: .04em;
            font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0;
            padding: 10px 12px; white-space: nowrap;
        }
        .vio-fines-table tbody td {
            padding: 10px 12px; border-bottom: 1px solid #f1f5f9;
            vertical-align: middle; font-size: 13px; color: #0f172a;
            position: relative; z-index: 0;
        }
        .vio-fines-table tbody tr:hover td { background: #f8fafc; }
        .vio-fines-table tbody tr.vio-row-inactive td {
            background: #f1f5f9 !important;
            color: #94a3b8 !important;
        }
        .vio-fines-table tbody tr.vio-row-inactive:hover td {
            background: #e2e8f0 !important;
        }
        .vio-fines-table tbody tr.vio-row-inactive .vio-chip {
            opacity: 0.75;
            filter: grayscale(0.35);
        }
        .vio-fines-table tbody tr.vio-row-inactive input[type="checkbox"] {
            cursor: not-allowed;
            opacity: 0.45;
        }
        .vio-fines-table tbody tr:last-child td { border-bottom: 0; }
        .vio-chip {
            display: inline-flex; align-items: center; border-radius: 999px;
            padding: 2px 8px; font-size: 11px; font-weight: 600; line-height: 1.35;
            white-space: nowrap;
        }
        .vio-chip-slate { background: #f1f5f9; color: #475569; }
        .vio-chip-ok { background: #ecfdf5; color: #047857; }
        .vio-chip-warn { background: #fffbeb; color: #b45309; border: 1px solid #fbbf24; }
        .vio-chip-critical {
            background: #fef2f2; color: #b91c1c; border: 1px solid #f87171; font-weight: 800;
        }
        .vio-chip-pulse { animation: vio-pulse 1.4s ease-in-out infinite; }
        @keyframes vio-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, .35); }
            50% { box-shadow: 0 0 0 5px rgba(220, 38, 38, 0); }
        }
        .vio-chip-sky { background: #f0f9ff; color: #0369a1; }
        .vio-chip-teal { background: #f0fdfa; color: #0f766e; }
        .vio-chip-violet { background: #f5f3ff; color: #6d28d9; }
        .vio-chip-indigo { background: #eef2ff; color: #4338ca; }
        .vio-tip { position: relative; display: block; max-width: 28rem; z-index: 1; }
        .vio-tip-trigger {
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            font-weight: 600; cursor: default; border-bottom: 1px dotted #94a3b8;
        }
        .vio-tip-panel {
            display: none; position: absolute; left: 0; bottom: calc(100% + 8px);
            z-index: 5; width: min(28rem, 70vw); max-height: 14rem; overflow: auto;
            padding: 12px 14px; border-radius: 10px; background: #0f172a; color: #e2e8f0;
            font-size: 12px; line-height: 1.45; white-space: pre-wrap; word-break: break-word;
            box-shadow: 0 16px 40px rgba(15,23,42,.28);
            pointer-events: none;
        }
        .vio-tip:hover .vio-tip-panel,
        .vio-tip:focus-within .vio-tip-panel { display: block; }
        .vio-footer-bar {
            z-index: 40 !important;
            background: rgba(255,255,255,.92) !important;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -12px 40px rgba(34,58,78,.1);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }
        .vio-hero {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            background:
                radial-gradient(120% 140% at 100% 0%, rgba(46,204,113,.12), transparent 45%),
                linear-gradient(135deg, #1a2d3d 0%, #223a4e 48%, #2a4a63 100%);
            color: #e8eef3;
            box-shadow: 0 18px 40px rgba(34,58,78,.18);
            padding: 22px 24px 20px;
        }
        .vio-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, transparent 0%, rgba(255,255,255,.04) 50%, transparent 100%);
            pointer-events: none;
        }
        .vio-hero-top {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px 16px;
        }
        .vio-hero-kicker {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(232,238,243,.62);
            margin: 0 0 6px;
        }
        .vio-hero-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 650;
            letter-spacing: -.01em;
            color: #fff;
            line-height: 1.25;
        }
        .vio-mailbox-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.16);
            color: #fff;
            font-size: 12.5px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            backdrop-filter: blur(6px);
        }
        .vio-mailbox-pill svg { flex-shrink: 0; opacity: .85; }
        .vio-mailbox-pill span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .vio-steps {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }
        .vio-step {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            font-size: 12px;
            color: rgba(255,255,255,.92);
            line-height: 1.2;
        }
        .vio-step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 999px;
            background: rgba(46,204,113,.95);
            color: #0f2918;
            font-size: 10px;
            font-weight: 800;
        }
        .vio-step strong { font-weight: 700; color: #fff; }
        .vio-hero-note {
            position: relative;
            margin: 14px 0 0;
            font-size: 12px;
            line-height: 1.5;
            color: rgba(232,238,243,.68);
            max-width: 52rem;
        }
        .vio-hero-note a {
            color: #9fe8bf;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px solid rgba(159,232,191,.35);
        }
        .vio-hero-note a:hover { color: #c7f5d9; }
        .vio-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
            margin-top: 1.25rem;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #e8eef3;
            box-shadow: 0 1px 2px rgba(34,58,78,.04);
        }
        .vio-empty {
            margin-top: 1.5rem;
            border-radius: 14px;
            border: 1px dashed #d5dee7;
            background: linear-gradient(180deg, #fbfcfd, #f5f8fa);
            padding: 28px 20px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }
    </style>

    <div class="vio-hero mb-5">
        <div class="vio-hero-top">
            <div>
                <p class="vio-hero-kicker">Mailbox</p>
                <h2 class="vio-hero-title">New fines</h2>
            </div>
            <div class="vio-mailbox-pill" title="Connected mailbox">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                    <path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" />
                    <path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" />
                </svg>
                <span>{{ $mailbox ?: 'Not configured' }}</span>
            </div>
        </div>

        <div class="vio-steps" aria-label="Workflow">
            <div class="vio-step"><span class="vio-step-num">1</span><span><strong>Fetch</strong> inbox</span></div>
            <div class="vio-step"><span class="vio-step-num">2</span><span>Select rows</span></div>
            <div class="vio-step"><span class="vio-step-num">3</span><span><strong>Find driver</strong> → VIO</span></div>
            <div class="vio-step"><span class="vio-step-num">4</span><span><strong>Wheelsys import</strong></span></div>
        </div>

        <p class="vio-hero-note">
            Re-fetch marks rows already saved as <strong style="color:#fff">Already have</strong>.
            Use <strong style="color:#fff">Details</strong> for rental fixes or ANR assist.
            Continue in
            <a href="{{ \App\Filament\Pages\WheelsExcelExport::getUrl() }}">Wheelsys import</a>.
        </p>
    </div>

    <form wire:submit="fetch" class="vio-toolbar">
        <div class="min-w-[12rem] flex-1">
            {{ $this->form }}
        </div>
        <x-filament::button type="submit" icon="heroicon-o-arrow-path" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="fetch">Fetch fine emails</span>
            <span wire:loading wire:target="fetch">Connecting…</span>
        </x-filament::button>
    </form>

    @if ($lastError)
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
            {{ $lastError }}
        </div>
    @endif

    @php
        $filteredResults = $this->filteredResults;
        $filterActive = filled($data['authority_id'] ?? null);
    @endphp

    @if (count($results) > 0)
        <div class="mt-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <label class="block min-w-[16rem] flex-1 text-sm">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Authority</span>
                <select
                    wire:model.live="data.authority_id"
                    class="mt-1.5 w-full rounded-lg border-slate-300 text-sm shadow-sm"
                    style="padding: 10px 12px;"
                >
                    @foreach ($this->authorityFilterOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <span class="mt-1.5 block text-xs text-slate-500">Filters this list only — does not re-scan inbox.</span>
            </label>
            @if ($filterActive)
                <x-filament::button size="sm" color="gray" wire:click="clearAuthorityFilter">
                    Clear filter
                </x-filament::button>
            @endif
            <p class="ml-auto text-xs text-slate-500 pb-1">
                Showing <strong class="text-slate-800">{{ count($filteredResults) }}</strong> of {{ count($results) }}
                @if ($filterActive)
                    · filtered
                @endif
            </p>
        </div>

        @if (count($filteredResults) > 0)
            <div class="mt-3 overflow-x-auto overflow-y-visible rounded-xl bg-white ring-1 ring-slate-200" style="margin-bottom: 5.5rem; box-shadow: 0 8px 24px rgba(34,58,78,.06);">
                <table class="vio-fines-table w-full min-w-[920px] text-left">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;"></th>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Authority</th>
                            <th>Informavimas</th>
                            <th>Plate / rental</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filteredResults as $index => $row)
                            @php
                                $lookup = $row['lookup_status'] ?? null;
                                $msgId = (string) ($row['id'] ?? '');
                                $alreadyHave = ! empty($row['in_vio'])
                                    || ! empty($row['in_excel_queue'])
                                    || ! empty($row['excel_exported']);
                                $fromLabel = trim((string) ($row['from_name'] ?? ''));
                                if ($fromLabel === '') {
                                    $fromLabel = trim((string) ($row['from_email'] ?? ''));
                                }
                                $fromEmail = trim((string) ($row['from_email'] ?? ''));
                                $preview = trim((string) ($row['preview'] ?? ''));
                                $subject = trim((string) ($row['subject'] ?? ''));
                            @endphp
                            <tr
                                wire:key="fine-{{ $msgId !== '' ? $msgId : $index }}"
                                @class(['vio-row-inactive' => $alreadyHave])
                            >
                                <td>
                                    @if ($msgId !== '')
                                        <input
                                            type="checkbox"
                                            value="{{ $msgId }}"
                                            wire:model.live="selectedIds"
                                            class="rounded border-slate-300"
                                            @disabled($alreadyHave)
                                        />
                                    @endif
                                </td>
                                <td style="min-width: 9.5rem;">
                                    <div style="font-size: 11px; color: #64748b; font-variant-numeric: tabular-nums;">
                                        {{ $row['received_at_local'] ?? '—' }}
                                    </div>
                                    <div style="margin-top: 2px; font-weight: 600; max-width: 12rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                         @if ($fromEmail !== '') title="{{ $fromEmail }}" @endif>
                                        {{ $fromLabel !== '' ? $fromLabel : '—' }}
                                    </div>
                                </td>
                                <td>
                                    @if ($preview !== '')
                                        <div class="vio-tip" tabindex="0">
                                            <div class="vio-tip-trigger">{{ $subject !== '' ? $subject : '(no subject)' }}</div>
                                            <div class="vio-tip-panel">{{ $preview }}</div>
                                        </div>
                                    @else
                                        <div style="font-weight: 600; max-width: 28rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            {{ $subject !== '' ? $subject : '(no subject)' }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if (($row['authority_name'] ?? '—') !== '—')
                                        <span class="vio-chip vio-chip-ok">{{ $row['authority_name'] }}</span>
                                    @else
                                        <span style="color: #cbd5e1;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @include('filament.partials.informavimo-badge', [
                                        'deadline' => \App\Support\InformavimoTerminas::forAuthorityId(
                                            isset($row['authority_id']) ? (int) $row['authority_id'] : null,
                                            $row['received_at'] ?? ($row['received_at_local'] ?? null),
                                            ! empty($row['email_sent']) || ! empty($row['epaslaugos_done']),
                                        ),
                                    ])
                                </td>
                                <td style="min-width: 8rem;">
                                    <div style="font-weight: 700; letter-spacing: .02em;">{{ ($row['vehicle'] ?? '') !== '' ? $row['vehicle'] : '—' }}</div>
                                    @if (filled($row['driver_name'] ?? null) || filled($row['lookup_detail'] ?? null) || filled($row['reference'] ?? null))
                                        <div style="margin-top: 2px; font-size: 11px; color: #64748b; max-width: 12rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            {{ $row['driver_name'] ?? ($row['lookup_detail'] ?? ($row['reference'] ?? '')) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @if (! empty($row['excel_exported']))
                                            <span class="vio-chip vio-chip-sky">Already have · Prepared</span>
                                        @elseif (! empty($row['in_excel_queue']))
                                            <span class="vio-chip vio-chip-indigo">Already have · Ready</span>
                                        @elseif (! empty($row['in_vio']) && $lookup === 'found')
                                            <span class="vio-chip vio-chip-ok">Already have · Found</span>
                                        @elseif (! empty($row['in_vio']) && $lookup === 'not_found')
                                            <span class="vio-chip vio-chip-warn">Already have · Not found</span>
                                        @elseif (! empty($row['in_vio']))
                                            <span class="vio-chip vio-chip-indigo">Already have</span>
                                        @elseif ($lookup === 'found')
                                            <span class="vio-chip vio-chip-ok">Found</span>
                                        @elseif ($lookup === 'not_found')
                                            <span class="vio-chip vio-chip-warn">Not found</span>
                                        @else
                                            <span class="vio-chip vio-chip-slate">New</span>
                                        @endif
                                    </div>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <x-filament::button size="sm" color="gray" wire:click="openDetails({{ $index }})">Details</x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-2 text-sm text-gray-500">No messages for the selected authority. Clear the filter or fetch again.</p>
        @endif

        @unless ($showPreview)
            <div class="vio-footer-bar fixed inset-x-0 bottom-0">
                <div class="flex max-w-full flex-wrap items-center gap-2 px-3 py-3 lg:px-4">
                    <p class="mr-auto text-xs text-slate-500">
                        Selected <strong class="text-slate-800">{{ count($selectedIds) }}</strong>
                    </p>
                    <x-filament::button size="sm" color="gray" wire:click="toggleSelectAll">All / None</x-filament::button>
                    <span class="hidden h-5 w-px bg-slate-200 sm:block"></span>
                    <x-filament::button size="sm" color="primary" wire:click="findRentalsSelected" wire:loading.attr="disabled" wire:target="findRentalsSelected, openDetails, searchWheelsys">
                        <span wire:loading.remove wire:target="findRentalsSelected">Find driver</span>
                        <span wire:loading wire:target="findRentalsSelected">Looking up…</span>
                    </x-filament::button>
                </div>
            </div>
        @endunless
    @elseif (! $lastError)
        <div class="vio-empty">
            Press <strong style="color:#223a4e">Fetch fine emails</strong> to load the inbox.
        </div>
    @endif

    @if ($showPreview && $previewDraft)
        <div
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto"
            style="background: rgba(15, 23, 42, 0.5); padding: 24px;"
            wire:click.self="closePreview"
        >
            <div
                class="w-full rounded-xl bg-white shadow-xl"
                style="max-width: 64rem; margin: 16px 0; box-shadow: 0 20px 40px rgba(15,23,42,.18); border: 1px solid rgba(15,23,42,.08);"
            >
                <div
                    class="flex items-start justify-between gap-4"
                    style="padding: 24px 28px; border-bottom: 1px solid #eef2f7;"
                >
                    <div style="padding-right: 12px;">
                        <h2 class="text-lg font-semibold text-gray-950" style="margin: 0;">
                            Details
                            @if (($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                                · ANR / ePaslaugos
                            @endif
                        </h2>
                        <p class="text-sm text-gray-500" style="margin: 8px 0 0;">
                            {{ $previewDraft['authority_name'] ?? '—' }}
                            @if (! empty($previewDraft['missing_fields']))
                                · still missing: {{ implode(', ', $previewDraft['missing_fields']) }}
                            @endif
                            @if (! empty($previewDraft['info_pdf_template']))
                                · PDF: {{ $previewDraft['info_pdf_file_name'] ?? 'reference.pdf' }}
                                @if (($previewDraft['info_pdf_template'] ?? '') === 'latvia')
                                    (+ Marksign)
                                @endif
                            @endif
                        </p>
                        @if (($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                            <p class="text-sm text-amber-700" style="margin: 8px 0 0;">
                                ANR/ROIK: ne siųsti el. laišku į no-reply. Duomenis teikti per
                                <strong>IRD ePaslaugas</strong> (assisted žemiau).
                            </p>
                        @endif
                    </div>
                    <x-filament::button size="sm" color="gray" wire:click="closePreview">Close</x-filament::button>
                </div>

                @php
                    $epaslaugosAssist = $this->epaslaugosAssist;
                    $latviaAssist = $this->latviaAssist;
                @endphp
                @if ($epaslaugosAssist)
                    <div
                        x-data="{
                            async copyText(text, label) {
                                try {
                                    await navigator.clipboard.writeText(text || '');
                                    $wire.notifyClipboardCopied(label || 'Copied');
                                } catch (e) {
                                    $wire.notifyClipboardCopied('Copy failed — select text manually');
                                }
                            }
                        }"
                        style="padding: 16px 28px; border-bottom: 1px solid #fde68a; background: #fffbeb;"
                    >
                        <p class="text-xs font-semibold uppercase text-amber-800" style="margin: 0 0 10px;">ePaslaugos assist · ROIK pildymas</p>
                        <div class="flex flex-wrap items-center gap-2" style="margin-bottom: 12px;">
                            <label class="text-xs text-amber-900">
                                Asmens tipas
                                <select wire:model.live="epaslaugosPersonType" class="ml-2 rounded border-amber-300 text-xs">
                                    @foreach ($epaslaugosAssist['person_type_labels'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <x-filament::button
                                tag="a"
                                size="sm"
                                color="warning"
                                :href="$epaslaugosAssist['portal_url']"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open ePaslaugos
                            </x-filament::button>
                            <x-filament::button
                                size="sm"
                                color="gray"
                                type="button"
                                @click="copyText(@js($epaslaugosAssist['roik']), 'ROIK copied')"
                                :disabled="$epaslaugosAssist['roik'] === ''"
                            >
                                Copy ROIK{{ $epaslaugosAssist['roik'] !== '' ? ' ('.$epaslaugosAssist['roik'].')' : '' }}
                            </x-filament::button>
                            <x-filament::button
                                size="sm"
                                color="gray"
                                type="button"
                                @click="copyText(@js($epaslaugosAssist['vehicle']), 'Vehicle copied')"
                                :disabled="$epaslaugosAssist['vehicle'] === ''"
                            >
                                Copy plate{{ $epaslaugosAssist['vehicle'] !== '' ? ' ('.$epaslaugosAssist['vehicle'].')' : '' }}
                            </x-filament::button>
                            <x-filament::button
                                size="sm"
                                color="gray"
                                type="button"
                                @click="copyText(@js($epaslaugosAssist['driver_block']), 'Driver block copied')"
                                :disabled="$epaslaugosAssist['driver_block'] === ''"
                            >
                                Copy all fields
                            </x-filament::button>
                        </div>
                        @if (! empty($epaslaugosAssist['fields']))
                            <div class="grid gap-2 sm:grid-cols-2" style="margin-bottom: 12px;">
                                @foreach ($epaslaugosAssist['fields'] as $field)
                                    <div class="flex items-center justify-between gap-2 rounded-lg bg-white ring-1 ring-amber-200" style="padding: 8px 10px;">
                                        <div class="min-w-0">
                                            <div class="text-[10px] uppercase text-amber-700">{{ $field['label'] }}</div>
                                            <div class="truncate text-xs font-semibold text-slate-800">{{ $field['value'] }}</div>
                                        </div>
                                        <x-filament::button
                                            size="xs"
                                            color="gray"
                                            type="button"
                                            @click="copyText(@js($field['value']), @js($field['label'].' copied'))"
                                        >
                                            Copy
                                        </x-filament::button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <ol class="text-xs text-amber-900" style="margin: 0; padding-left: 1.1rem; line-height: 1.55;">
                            @foreach ($epaslaugosAssist['checklist'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                        <p class="text-xs font-semibold text-amber-800" style="margin: 12px 0 6px;">Atmesti (periodinis)</p>
                        <ol class="text-xs text-amber-900" style="margin: 0; padding-left: 1.1rem; line-height: 1.55;">
                            @foreach ($epaslaugosAssist['rejected_checklist'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if ($latviaAssist)
                    <div
                        x-data="{
                            async copyText(text, label) {
                                try {
                                    await navigator.clipboard.writeText(text || '');
                                    $wire.notifyClipboardCopied(label || 'Copied');
                                } catch (e) {
                                    $wire.notifyClipboardCopied('Copy failed');
                                }
                            }
                        }"
                        style="padding: 16px 28px; border-bottom: 1px solid #bfdbfe; background: #eff6ff;"
                    >
                        <p class="text-xs font-semibold uppercase text-blue-800" style="margin: 0 0 10px;">
                            Latvija CPP paketas
                            @if (! empty($latviaAssist['is_reminder']))
                                · PRIMINIMAS → E-CSDD
                            @endif
                        </p>
                        <div class="flex flex-wrap gap-2" style="margin-bottom: 12px;">
                            @if (! empty($latviaAssist['is_reminder']))
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    color="info"
                                    :href="$latviaAssist['ecsdd_url']"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open E-CSDD
                                </x-filament::button>
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    type="button"
                                    @click="copyText(@js($latviaAssist['case_no']), 'Case no copied')"
                                >
                                    Copy case no
                                </x-filament::button>
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    type="button"
                                    @click="copyText(@js($latviaAssist['vehicle']), 'Plate copied')"
                                >
                                    Copy plate
                                </x-filament::button>
                            @endif
                            <span class="text-xs text-blue-900" style="align-self:center;">
                                Įgaliojimas:
                                @if (! empty($latviaAssist['poa']['valid']))
                                    galioja iki {{ $latviaAssist['poa']['valid_until'] }}
                                @else
                                    <strong>nėra / nepasirašytas</strong> — Settings → Renew POA
                                @endif
                            </span>
                        </div>
                        <ol class="text-xs text-blue-900" style="margin: 0; padding-left: 1.1rem; line-height: 1.55;">
                            @foreach ($latviaAssist['checklist'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                        <div style="margin-top: 12px;">
                            <label class="block text-xs text-blue-900">
                                RNT PDF (nebūtina juridiniam; fiziniam — taip)
                                <input type="file" wire:model="latviaRntUpload" accept="application/pdf" class="mt-1 block w-full text-xs" />
                            </label>
                            <div wire:loading wire:target="latviaRntUpload" class="text-xs text-blue-700">Uploading RNT…</div>
                        </div>
                    </div>
                @endif

                @if (count($workflowSteps) > 0)
                    <div style="padding: 16px 28px; border-bottom: 1px solid #eef2f7; background: #f8fafc;">
                        <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0 0 10px;">Pipeline</p>
                        <ol class="grid gap-2 text-xs text-slate-700 sm:grid-cols-2" style="margin: 0; padding: 0; list-style: none;">
                            @foreach ($workflowSteps as $step)
                                <li style="display:flex;gap:8px;align-items:flex-start;">
                                    <span style="min-width:1rem;font-weight:700;color:{{ ! empty($step['ok']) ? '#15803d' : '#94a3b8' }};">
                                        {{ ! empty($step['ok']) ? '✓' : '○' }}
                                    </span>
                                    <span>
                                        <strong>{{ $step['key'] }}</strong>
                                        — {{ $step['detail'] ?? '' }}
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                <div
                    class="grid gap-3 sm:grid-cols-3"
                    style="padding: 16px 28px; border-bottom: 1px solid #eef2f7; background: #f8fafc;"
                >
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Reference</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">{{ ($previewDraft['reference'] ?? '') !== '' ? $previewDraft['reference'] : '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Vehicle</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">{{ ($previewDraft['vehicle_hint'] ?? '') !== '' ? $previewDraft['vehicle_hint'] : '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Ticket date</div>
                        <div class="mt-1 text-sm font-medium text-gray-900">
                            {{ ($previewDraft['ticket_date_hint'] ?? '') !== '' ? $previewDraft['ticket_date_hint'] : '—' }}
                            @if (($previewDraft['ticket_time_hint'] ?? '') !== '')
                                <span class="text-gray-500">{{ $previewDraft['ticket_time_hint'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-6 lg:grid-cols-2"
                    style="padding: 24px 28px; border-bottom: 1px solid #eef2f7;"
                >
                    <div class="space-y-4 text-sm">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-gray-500">To</span>
                            <input
                                type="email"
                                wire:model="previewDraft.to"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm"
                                style="padding: 10px 12px;"
                                placeholder="{{ ($previewDraft['reply_channel'] ?? '') === 'epaslaugos' ? '— ePaslaugos (ne el. paštas) —' : '' }}"
                                @disabled(($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                            />
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-gray-500">Subject</span>
                            <input type="text" wire:model.blur="previewDraft.subject" wire:change="refreshPreviewHtml" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm" style="padding: 10px 12px;" />
                        </label>
                        <div class="text-xs text-gray-500" style="padding-top: 4px; line-height: 1.5;">
                            From: {{ $previewDraft['from_name'] ?? '' }} &lt;{{ $previewDraft['from_email'] ?? '' }}&gt;
                            @if (! empty($previewDraft['reply_to']))
                                · Reply-To: {{ $previewDraft['reply_to'] }}
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200" style="padding: 16px;">
                        <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0 0 12px;">Wheelsys fill</p>
                        <div class="flex flex-wrap gap-2">
                            <input
                                type="text"
                                wire:model="searchVehicle"
                                placeholder="Vehicle e.g. MAB841"
                                class="min-w-[8rem] flex-1 rounded-lg border-gray-300 text-sm uppercase shadow-sm"
                                style="padding: 10px 12px;"
                            />
                            <input
                                type="date"
                                wire:model="searchTicketDate"
                                class="rounded-lg border-gray-300 text-sm shadow-sm"
                                style="padding: 10px 12px;"
                            />
                            <x-filament::button size="sm" color="gray" wire:click="clearSearchTicketDate" title="Clear date — search without date filter">
                                Reset date
                            </x-filament::button>
                            <x-filament::button size="sm" wire:click="searchWheelsys" wire:loading.attr="disabled">
                                Search
                            </x-filament::button>
                        </div>

                        @if (count($wheelsysResults) > 0)
                            <div class="mt-3 max-h-40 overflow-auto rounded-lg bg-white ring-1 ring-gray-200">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 text-gray-500">
                                        <tr>
                                            <th style="padding: 8px 10px;"></th>
                                            <th style="padding: 8px 10px;">Src</th>
                                            <th style="padding: 8px 10px;">Date</th>
                                            <th style="padding: 8px 10px;">RNT / Ref</th>
                                            <th style="padding: 8px 10px;">Driver</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($wheelsysResults as $wi => $wrow)
                                            @php
                                                $dateLabel = '—';
                                                if (($wrow['_source'] ?? '') === 'rental') {
                                                    $from = substr((string) ($wrow['date_from'] ?? ''), 0, 10);
                                                    $to = substr((string) ($wrow['date_to'] ?? ''), 0, 10);
                                                    $dateLabel = trim($from.($from !== '' && $to !== '' ? ' → ' : '').$to) ?: '—';
                                                } elseif (filled($wrow['ticket_date'] ?? null)) {
                                                    $dateLabel = (string) $wrow['ticket_date'];
                                                }
                                            @endphp
                                            <tr
                                                wire:key="ws-row-{{ $wi }}"
                                                style="cursor:pointer;{{ (int) $wheelsysSelectedIndex === (int) $wi ? 'background:#eef6ff;' : '' }}"
                                                wire:click="$set('wheelsysSelectedIndex', {{ $wi }})"
                                            >
                                                <td style="padding: 8px 10px;">
                                                    <input
                                                        type="radio"
                                                        name="wheelsysSelectedIndex"
                                                        value="{{ $wi }}"
                                                        wire:model.live="wheelsysSelectedIndex"
                                                        style="cursor:pointer;"
                                                    />
                                                </td>
                                                <td style="padding: 8px 10px;">{{ ($wrow['_source'] ?? '') === 'rental' ? 'Rental' : (($wrow['_source'] ?? '') === 'violation' ? 'TV' : '—') }}</td>
                                                <td style="padding: 8px 10px; white-space: nowrap;">{{ $dateLabel }}</td>
                                                <td style="padding: 8px 10px;">{{ $wrow['agr_no'] ?? $wrow['ticket_number'] ?? '—' }}</td>
                                                <td style="padding: 8px 10px;">{{ $wrow['driver'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($wheelsysSelected)
                            <p class="text-xs text-emerald-700" style="margin: 12px 0 0;">
                                Selected: {{ $wheelsysSelected['vehicle'] ?? '' }} · {{ $wheelsysSelected['driver'] ?? '' }} · {{ $wheelsysSelected['birth_date'] ?? '—' }}
                            </p>
                        @endif
                    </div>
                </div>

                <div style="padding: 24px 28px; border-bottom: 1px solid #eef2f7;">
                    <div class="flex items-center justify-between gap-3" style="margin: 0 0 12px;">
                        <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0;">Email HTML</p>
                        @if (! $showTemplateEditor)
                            <x-filament::button size="sm" color="gray" wire:click="openTemplateEditor">
                                Edit text
                            </x-filament::button>
                        @else
                            <x-filament::button size="sm" color="gray" wire:click="closeTemplateEditor">
                                Done editing
                            </x-filament::button>
                        @endif
                    </div>

                    <div class="overflow-auto rounded-lg border border-gray-200 bg-white" style="max-height: 420px; padding: 8px;">
                        {!! $previewDraft['body_html'] ?? '' !!}
                    </div>

                    @if ($showTemplateEditor)
                        <label class="block text-sm" style="margin-top: 16px;">
                            <span class="text-xs font-semibold uppercase text-gray-500">Template text</span>
                            <textarea
                                wire:model.blur="previewDraft.body_text"
                                wire:change="refreshPreviewHtml"
                                rows="8"
                                class="mt-2 w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm"
                                style="padding: 12px 14px;"
                            ></textarea>
                        </label>
                    @endif
                </div>

                <div class="flex flex-wrap justify-end gap-2" style="padding: 20px 28px;">
                    <x-filament::button color="gray" wire:click="closePreview">Close</x-filament::button>
                    <x-filament::button color="gray" wire:click="createInWheelsys">Create in Wheels</x-filament::button>
                    <x-filament::button color="gray" wire:click="markWheelsCreated">Mark Wheels done</x-filament::button>
                    @if (($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                        <x-filament::button color="warning" wire:click="markEpaslaugosDone">
                            Mark ePaslaugos done
                        </x-filament::button>
                    @elseif (($previewDraft['info_pdf_template'] ?? '') === 'latvia')
                        <x-filament::button color="info" wire:click="prepareLatviaMarksign" wire:loading.attr="disabled" wire:target="prepareLatviaMarksign">
                            <span wire:loading.remove wire:target="prepareLatviaMarksign">MarkSign LV raštą</span>
                            <span wire:loading wire:target="prepareLatviaMarksign">MarkSign…</span>
                        </x-filament::button>
                        <x-filament::button color="primary" wire:click="sendLatviaPackage" wire:loading.attr="disabled" wire:target="sendLatviaPackage">
                            <span wire:loading.remove wire:target="sendLatviaPackage">Siųsti paketą į kanceleja</span>
                            <span wire:loading wire:target="sendLatviaPackage">Siunčiama…</span>
                        </x-filament::button>
                    @else
                        <x-filament::button color="primary" wire:click="sendReply" wire:loading.attr="disabled" wire:target="sendReply">
                            <span wire:loading.remove wire:target="sendReply">
                                @if (! empty($previewDraft['info_pdf_template']))
                                    Send reply + PDF
                                @else
                                    Send reply
                                @endif
                            </span>
                            <span wire:loading wire:target="sendReply">Sending…</span>
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
