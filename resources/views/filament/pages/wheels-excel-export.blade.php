<x-filament-panels::page>
    <style>
        .vio-import-hero {
            position: relative; overflow: hidden; border-radius: 16px;
            background:
                radial-gradient(120% 140% at 100% 0%, rgba(46,204,113,.12), transparent 45%),
                linear-gradient(135deg, #1a2d3d 0%, #223a4e 48%, #2a4a63 100%);
            color: #e8eef3; box-shadow: 0 18px 40px rgba(34,58,78,.18);
            padding: 20px 24px; margin-bottom: 1.25rem;
        }
        .vio-import-kicker {
            font-size: 11px; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: rgba(232,238,243,.62); margin: 0 0 6px;
        }
        .vio-import-title { margin: 0; font-size: 1.15rem; font-weight: 650; color: #fff; }
        .vio-import-note { margin: 10px 0 0; font-size: 12px; color: rgba(232,238,243,.7); max-width: 40rem; line-height: 1.45; }
        .vio-fines-table { border-collapse: separate; border-spacing: 0; }
        .vio-fines-table thead th {
            position: sticky; top: 0; z-index: 1;
            background: #f8fafc; font-size: 11px; letter-spacing: .04em;
            font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0;
            padding: 10px 12px; white-space: nowrap;
        }
        .vio-fines-table tbody td {
            padding: 10px 12px; border-bottom: 1px solid #f1f5f9;
            vertical-align: middle; font-size: 13px; color: #0f172a;
        }
        .vio-fines-table tbody tr:hover td { background: #f8fafc; }
        .vio-fines-table tbody tr.vio-row-inactive td {
            background: #f1f5f9 !important; color: #94a3b8 !important;
        }
        .vio-fines-table tbody tr.vio-row-inactive input[type="checkbox"] {
            cursor: not-allowed; opacity: 0.45;
        }
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
        .vio-footer-bar {
            z-index: 40 !important;
            background: rgba(255,255,255,.92) !important;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -12px 40px rgba(34,58,78,.1);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }
    </style>

    <div class="vio-import-hero">
        <p class="vio-import-kicker">Fines with driver</p>
        <h2 class="vio-import-title">Wheelsys import</h2>
        <p class="vio-import-note">
            Select ready fines → <strong style="color:#fff">Generate import file</strong> →
            <strong style="color:#fff">Start import</strong> in Wheels → <strong style="color:#fff">Update Wheelsys status</strong>.
        </p>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label class="block min-w-[14rem] flex-1 text-sm">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Import file</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="fileFilterQuery"
                placeholder="Search file…"
                class="mt-1.5 w-full rounded-lg border-slate-300 text-sm shadow-sm"
                style="padding: 8px 12px;"
            >
            <select
                wire:model.live="fileFilter"
                class="mt-1.5 w-full rounded-lg border-slate-300 text-sm shadow-sm"
                style="padding: 10px 12px;"
            >
                @foreach ($this->filteredFileFilterOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block min-w-[11rem] text-sm">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</span>
            <select
                wire:model.live="statusFilter"
                class="mt-1.5 w-full rounded-lg border-slate-300 text-sm shadow-sm"
                style="padding: 10px 12px;"
            >
                <option value="">{{ $showImported ? 'All statuses' : 'Any unimported' }}</option>
                <option value="ready">Ready</option>
                <option value="prepared">In import file</option>
                <option value="missing">Missing in Wheels</option>
                @if ($showImported)
                    <option value="imported">In Wheels</option>
                @endif
            </select>
        </label>
        @if ($fileFilter !== '' || $statusFilter !== '' || $fileFilterQuery !== '')
            <x-filament::button size="sm" color="gray" wire:click="clearFilters">Clear filters</x-filament::button>
        @endif
        <div class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-1 pb-1 text-xs text-slate-500">
            <span>
                Showing <strong class="text-slate-800">{{ count($this->logs) }}</strong>
                @unless ($showImported)
                    <span class="text-slate-400">· unimported only</span>
                @else
                    <span class="text-slate-400">· all</span>
                @endunless
            </span>
            @if ($showImported)
                <button
                    type="button"
                    class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-2 hover:text-sky-900"
                    wire:click="showUnimportedOnly"
                >Only unimported</button>
            @else
                <button
                    type="button"
                    class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-2 hover:text-sky-900"
                    wire:click="showAllFines"
                >Show all</button>
            @endif
        </div>
    </div>

    @if (count($this->logs) === 0)
        <p class="text-sm text-gray-500">
            Nothing here yet. On <strong>New fines</strong> use Fetch + Find driver first.
        </p>
    @else
        <div class="overflow-x-auto overflow-y-visible rounded-xl bg-white ring-1 ring-slate-200" style="margin-bottom: 5.5rem; box-shadow: 0 8px 24px rgba(34,58,78,.06);">
            <table class="vio-fines-table w-full min-w-[980px] text-left">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;"></th>
                        <th>Received</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Reference</th>
                        <th>Authority</th>
                        <th>Informavimas</th>
                        <th>Status</th>
                        <th>Import file</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->logs as $log)
                        @php
                            $selectable = $this->rowIsSelectable($log);
                            $status = $this->rowStatusLabel($log);
                            $fileLabel = $log->wheels_export_file_name
                                ?: ($log->wheels_export_batch ? 'wheels-export-'.$log->wheels_export_batch.'.xlsx' : null);
                        @endphp
                        <tr
                            wire:key="import-{{ $log->id }}"
                            @class(['vio-row-inactive' => ! $selectable])
                        >
                            <td>
                                <input
                                    type="checkbox"
                                    value="{{ $log->id }}"
                                    wire:model.live="selectedIds"
                                    class="rounded border-slate-300"
                                    @disabled(! $selectable && $log->wheels_import_status === \App\Models\FineProcessLog::WHEELS_IMPORT_IMPORTED)
                                />
                            </td>
                            <td style="min-width: 8rem;">
                                <div style="font-size: 11px; color: #64748b; font-variant-numeric: tabular-nums;">
                                    {{ optional($log->received_at)->timezone(config('app.timezone'))?->format('Y-m-d H:i') ?? '—' }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; letter-spacing: .02em;">{{ $log->vehicle ?: '—' }}</div>
                                @if ($log->ticket_date)
                                    <div style="margin-top: 2px; font-size: 11px; color: #64748b;">
                                        {{ $log->ticket_date->format('Y-m-d') }}
                                        @if ($log->ticket_time) {{ $log->ticket_time }} @endif
                                    </div>
                                @endif
                            </td>
                            <td>{{ $log->driver_name ?: '—' }}</td>
                            <td>{{ $log->reference ?: '—' }}</td>
                            <td>
                                @if ($log->authority_name)
                                    <span class="vio-chip vio-chip-ok">{{ $log->authority_name }}</span>
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>
                            <td>
                                @include('filament.partials.informavimo-badge', [
                                    'deadline' => \App\Support\InformavimoTerminas::forLog($log),
                                ])
                            </td>
                            <td>
                                @if ($status === 'Ready')
                                    <span class="vio-chip vio-chip-slate">Ready</span>
                                @elseif ($status === 'In Wheels')
                                    <span class="vio-chip vio-chip-ok">In Wheels</span>
                                @elseif ($status === 'Missing in Wheels')
                                    <span class="vio-chip vio-chip-warn">Missing</span>
                                @else
                                    <span class="vio-chip vio-chip-sky">In import file</span>
                                @endif
                            </td>
                            <td>
                                @if ($fileLabel)
                                    @if ($log->wheels_export_sharepoint_url)
                                        <a
                                            href="{{ $log->wheels_export_sharepoint_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs font-medium text-sky-700 hover:underline"
                                            title="{{ $fileLabel }}"
                                        >{{ \Illuminate\Support\Str::limit($fileLabel, 28) }}</a>
                                    @else
                                        <span class="font-mono text-xs text-slate-600">{{ \Illuminate\Support\Str::limit($fileLabel, 28) }}</span>
                                    @endif
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="vio-footer-bar fixed inset-x-0 bottom-0">
            <div class="flex max-w-full flex-wrap items-center gap-2 px-3 py-3 lg:px-4">
                <p class="mr-auto text-xs text-slate-500">
                    Selected <strong class="text-slate-800">{{ count($selectedIds) }}</strong>
                </p>
                <x-filament::button size="sm" color="gray" wire:click="toggleSelectAll">All / None</x-filament::button>
                @if (count($selectedIds) > 0)
                    <x-filament::button
                        size="sm"
                        color="success"
                        wire:click="generateImportFile"
                        wire:loading.attr="disabled"
                        wire:target="generateImportFile"
                    >
                        <span wire:loading.remove wire:target="generateImportFile">Generate import file</span>
                        <span wire:loading wire:target="generateImportFile">Generating…</span>
                    </x-filament::button>
                @endif
                <span class="hidden h-5 w-px bg-slate-200 sm:block"></span>
                <x-filament::button size="sm" color="primary" wire:click="startImport">Start import</x-filament::button>
                <x-filament::button
                    size="sm"
                    color="warning"
                    wire:click="verifyInWheelsys"
                    wire:loading.attr="disabled"
                    wire:target="verifyInWheelsys"
                >
                    <span wire:loading.remove wire:target="verifyInWheelsys">Update Wheelsys status</span>
                    <span wire:loading wire:target="verifyInWheelsys">Updating…</span>
                </x-filament::button>
            </div>
        </div>
    @endif

    @if ($showImportPicker)
        <div
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto"
            style="background: rgba(15, 23, 42, 0.55); padding: 32px 20px;"
            wire:click.self="closeImportPicker"
        >
            <div
                class="w-full rounded-2xl bg-white"
                style="max-width: 52rem; margin: 12px 0; border: 1px solid rgba(15,23,42,.08); box-shadow: 0 24px 48px rgba(15,23,42,.2);"
            >
                <div
                    class="flex items-start justify-between gap-4"
                    style="padding: 28px 32px 20px; border-bottom: 1px solid #eef2f7;"
                >
                    <div style="padding-right: 8px;">
                        <h2 class="text-lg font-semibold text-gray-950" style="margin: 0; letter-spacing: -.01em;">
                            Start import
                        </h2>
                        <p class="text-sm text-gray-500" style="margin: 8px 0 0; line-height: 1.5;">
                            Choose the import file, review fines inside, then Import.
                        </p>
                    </div>
                    <div style="padding-top: 2px;">
                        <x-filament::button size="sm" color="gray" wire:click="closeImportPicker">Close</x-filament::button>
                    </div>
                </div>

                <div style="padding: 24px 32px 8px;">
                    <div class="block text-sm" style="margin-bottom: 16px;">
                        <div class="flex flex-wrap items-center justify-between gap-2" style="margin-bottom: 8px;">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Import file</span>
                            <label class="flex items-center gap-2 text-xs text-slate-600" style="user-select: none;">
                                <input type="checkbox" wire:model.live="importPickerShowImported" class="rounded border-slate-300 text-primary-600 shadow-sm">
                                <span>Show imported files</span>
                            </label>
                        </div>
                        @php
                            $pickerChoices = $this->filteredImportFileChoices;
                        @endphp

                        @if ($pickerChoices === [])
                            <p class="text-sm text-slate-500" style="margin: 12px 0 0;">
                                @if ($importPickerShowImported)
                                    No import files yet.
                                @else
                                    No unimported files.
                                    <button type="button" class="font-semibold text-sky-700 underline" wire:click="enableImportedFiles">Show imported files</button>
                                @endif
                            </p>
                        @else
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="importPickerQuery"
                                placeholder="Search by file name…"
                                class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm"
                                style="padding: 10px 14px;"
                            >
                            <select
                                wire:model.live="importPickerBatch"
                                class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm"
                                style="padding: 12px 14px;"
                            >
                                @foreach ($pickerChoices as $batch => $file)
                                    <option value="{{ $batch }}">{{ $file['label'] }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if ($this->importPickerSelectedFile)
                            <p class="text-xs text-slate-500" style="margin: 10px 0 0; line-height: 1.45;">
                                {{ $this->importPickerSelectedFile['count'] }} fine(s) in this file
                                @if ($this->importPickerSelectedFile['prepared_at'])
                                    · prepared {{ $this->importPickerSelectedFile['prepared_at'] }}
                                @endif
                                @if (($this->importPickerSelectedFile['imported_count'] ?? 0) > 0)
                                    · {{ $this->importPickerSelectedFile['imported_count'] }} already In Wheels
                                @endif
                            </p>
                        @endif
                    </div>

                    @if ($this->importPickerStats['already_imported'])
                        <div
                            style="margin: 0 0 20px; padding: 14px 16px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px; line-height: 1.5;"
                        >
                            <strong>Already imported.</strong>
                            {{ $this->importPickerStats['imported'] }} of {{ $this->importPickerStats['total'] }} fine(s)
                            are already In Wheels. Re-importing may create duplicates.
                        </div>
                    @endif

                    <div style="margin-bottom: 8px;">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500" style="margin: 0 0 12px;">
                            Preview · {{ count($this->importPreviewLogs) }}
                        </p>
                        @if (count($this->importPreviewLogs) === 0)
                            <p class="text-sm text-slate-500" style="margin: 0; padding: 16px 0;">No fines in this file.</p>
                        @else
                            <div class="overflow-auto rounded-xl bg-white ring-1 ring-slate-200" style="max-height: 20rem;">
                                <table class="w-full text-left text-sm" style="border-collapse: separate; border-spacing: 0;">
                                    <thead class="sticky top-0 bg-slate-50 text-xs uppercase text-slate-500">
                                        <tr>
                                            <th style="padding: 12px 16px;">Vehicle</th>
                                            <th style="padding: 12px 16px;">Driver</th>
                                            <th style="padding: 12px 16px;">Reference</th>
                                            <th style="padding: 12px 16px;">Ticket date</th>
                                            <th style="padding: 12px 16px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->importPreviewLogs as $log)
                                            @php $status = $this->rowStatusLabel($log); @endphp
                                            <tr wire:key="preview-{{ $log->id }}" style="border-top: 1px solid #f1f5f9;">
                                                <td style="padding: 12px 16px; font-weight: 700; letter-spacing: .02em;">{{ $log->vehicle ?: '—' }}</td>
                                                <td style="padding: 12px 16px;">{{ $log->driver_name ?: '—' }}</td>
                                                <td style="padding: 12px 16px;">{{ $log->reference ?: '—' }}</td>
                                                <td style="padding: 12px 16px; white-space: nowrap;">
                                                    {{ optional($log->ticket_date)?->format('Y-m-d') ?: '—' }}
                                                </td>
                                                <td style="padding: 12px 16px;">
                                                    <span class="vio-chip {{ $status === 'In Wheels' ? 'vio-chip-ok' : ($status === 'Missing in Wheels' ? 'vio-chip-warn' : 'vio-chip-sky') }}">
                                                        {{ $status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div
                    class="flex flex-wrap justify-end gap-2"
                    style="padding: 20px 32px 28px; border-top: 1px solid #eef2f7; margin-top: 12px;"
                >
                    <x-filament::button color="gray" wire:click="closeImportPicker">Cancel</x-filament::button>
                    @if ($this->importPickerSelectedFile === null)
                        <button
                            type="button"
                            disabled
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white shadow-sm"
                            style="background:#16a34a; opacity:.45; cursor:not-allowed;"
                        >Import</button>
                    @else
                        {{-- Native control: Filament <x-filament::button> strips onclick; APP_URL port may also differ from the browser. --}}
                        <button
                            type="button"
                            onclick="window.open(@js($this->wheelsysImportSsoUrl()), '_blank', 'noopener,noreferrer')"
                            wire:click="confirmImport"
                            wire:loading.attr="disabled"
                            wire:target="confirmImport"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white shadow-sm hover:brightness-95"
                            style="background:#16a34a; border:0; cursor:pointer;"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-5 w-5" />
                            <span wire:loading.remove wire:target="confirmImport">Import</span>
                            <span wire:loading wire:target="confirmImport">Working…</span>
                        </button>
                        <a
                            href="{{ $this->wheelsysImportSsoUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="self-center text-xs font-semibold text-sky-700 underline"
                        >Open Wheelsys Import</a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
