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
        .vio-acc {
            border-radius: 14px; background: #fff; border: 1px solid #e2e8f0;
            box-shadow: 0 8px 24px rgba(34,58,78,.05); overflow: hidden;
        }
        .vio-acc + .vio-acc { margin-top: 12px; }
        .vio-acc-new { border-color: #86efac; box-shadow: 0 8px 24px rgba(46,204,113,.08); }
        .vio-acc-head {
            width: 100%; display: flex; align-items: center; gap: 12px; text-align: left;
            padding: 14px 16px; background: #f8fafc; border: 0; cursor: pointer;
        }
        .vio-acc-new .vio-acc-head { background: #ecfdf5; }
        .vio-acc-head:hover { background: #f1f5f9; }
        .vio-acc-new .vio-acc-head:hover { background: #d1fae5; }
        .vio-acc-chevron {
            width: 1.1rem; height: 1.1rem; color: #64748b; flex-shrink: 0;
            transition: transform .15s ease;
        }
        .vio-acc-open .vio-acc-chevron { transform: rotate(90deg); }
        .vio-acc-title { font-size: 13px; font-weight: 700; color: #0f172a; }
        .vio-acc-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
        .vio-acc-count {
            margin-left: auto; font-size: 11px; font-weight: 700; color: #475569;
            background: #e2e8f0; border-radius: 999px; padding: 2px 9px;
        }
        .vio-acc-new .vio-acc-count { background: #bbf7d0; color: #166534; }
        .vio-acc-status {
            flex-shrink: 0; font-size: 11px; font-weight: 700; border-radius: 999px;
            padding: 2px 9px; line-height: 1.35; white-space: nowrap;
        }
        .vio-acc-status-imported { background: #ecfdf5; color: #047857; border: 1px solid #86efac; }
        .vio-acc-status-not_imported { background: #fffbeb; color: #b45309; border: 1px solid #fbbf24; }
        .vio-acc-status-partial { background: #f0f9ff; color: #0369a1; border: 1px solid #7dd3fc; }
        .vio-acc-status-new { background: #f1f5f9; color: #475569; }
        .vio-acc-imported-when {
            flex-shrink: 0; font-size: 12px; font-weight: 800; color: #047857;
            font-variant-numeric: tabular-nums; letter-spacing: .01em;
            white-space: nowrap;
        }
        .vio-acc-day {
            border-radius: 16px; background: #fff; border: 1px solid #cbd5e1;
            box-shadow: 0 10px 28px rgba(34,58,78,.07); overflow: hidden;
        }
        .vio-acc-day + .vio-acc-day { margin-top: 14px; }
        .vio-acc-day > .vio-acc-head {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            padding: 16px 18px;
        }
        .vio-acc-day > .vio-acc-head:hover { background: #e8eef5; }
        .vio-acc-day .vio-acc-title { font-size: 15px; font-weight: 800; }
        .vio-acc-day-body { padding: 10px 12px 12px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .vio-acc-nested { border-color: #e2e8f0; box-shadow: 0 4px 14px rgba(34,58,78,.04); }
        .vio-acc-nested + .vio-acc-nested { margin-top: 8px; }
        .vio-acc-body { border-top: 1px solid #e2e8f0; }
        .vio-acc-actions { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .vio-list-tabs {
            display: inline-flex; gap: 4px; padding: 4px; border-radius: 12px;
            background: #f1f5f9; border: 1px solid #e2e8f0;
        }
        .vio-list-tab {
            display: inline-flex; align-items: center; gap: 8px;
            border: 0; background: transparent; cursor: pointer;
            border-radius: 9px; padding: 8px 14px;
            font-size: 13px; font-weight: 650; color: #64748b;
            transition: background .12s ease, color .12s ease, box-shadow .12s ease;
        }
        .vio-list-tab:hover { color: #0f172a; background: rgba(255,255,255,.55); }
        .vio-list-tab.is-active {
            background: #fff; color: #0f172a;
            box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 0 0 1px rgba(226,232,240,.9);
        }
        .vio-list-tab-count {
            font-size: 11px; font-weight: 700; line-height: 1;
            border-radius: 999px; padding: 3px 7px;
            background: #e2e8f0; color: #475569;
        }
        .vio-list-tab.is-active .vio-list-tab-count { background: #dbeafe; color: #1d4ed8; }
        .vio-list-tab.is-active[data-tab="imported"] .vio-list-tab-count {
            background: #d1fae5; color: #047857;
        }
    </style>

    <div class="vio-import-hero">
        <p class="vio-import-kicker">Fines with driver</p>
        <h2 class="vio-import-title">To Wheelsys</h2>
        <p class="vio-import-note">
            Ready fines → select → <strong style="color:#fff">Start import</strong> (builds Excel + opens Wheels).
            Already <strong style="color:#fff">Prepared</strong> → <strong style="color:#fff">Download &amp; open Wheels</strong>,
            then <strong style="color:#fff">Update status</strong> on that file.
        </p>
    </div>

    @include('filament.partials.vio-multi-select-assets')
    @php
        $fileFilterValues = array_values(array_map('strval', $fileFilters));
        $statusFilterValues = array_values(array_map('strval', $statusFilters));
        $fileOptionsJson = \Illuminate\Support\Js::from(
            collect($this->fileMultiSelectOptions)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values()->all()
        );
        $statusOptionsJson = \Illuminate\Support\Js::from(
            collect($this->statusMultiSelectOptions)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values()->all()
        );
        $filtersActive = $fileFilterValues !== [] || $statusFilterValues !== [];
        $tabCounts = $this->tabCounts;
        $isAwaitingTab = $listTab !== 'imported';
    @endphp

    <div class="mb-3">
        <div class="vio-list-tabs" role="tablist" aria-label="Import list">
            <button
                type="button"
                role="tab"
                data-tab="awaiting"
                class="vio-list-tab {{ $isAwaitingTab ? 'is-active' : '' }}"
                aria-selected="{{ $isAwaitingTab ? 'true' : 'false' }}"
                wire:click="setListTab('awaiting')"
            >
                Awaiting import
                <span class="vio-list-tab-count">{{ $tabCounts['awaiting'] }}</span>
            </button>
            <button
                type="button"
                role="tab"
                data-tab="imported"
                class="vio-list-tab {{ ! $isAwaitingTab ? 'is-active' : '' }}"
                aria-selected="{{ ! $isAwaitingTab ? 'true' : 'false' }}"
                wire:click="setListTab('imported')"
            >
                Imported
                <span class="vio-list-tab-count">{{ $tabCounts['imported'] }}</span>
            </button>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="block min-w-[16rem] flex-1 text-sm">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Import file</span>
            <div
                class="vio-ms mt-1.5"
                wire:key="file-filter-{{ md5(json_encode($fileFilterValues)) }}"
                x-data="vioMultiSelect({
                    options: {{ $fileOptionsJson }},
                    selected: @js($fileFilterValues),
                    placeholder: 'All import files',
                    onChange(values) { $wire.set('fileFilters', values) }
                })"
                @click.outside="open = false"
            >
                <button type="button" class="vio-ms-control" @click="open = !open; if (open) $nextTick(() => $refs.q.focus())">
                    <template x-if="selected.length === 0">
                        <span class="vio-ms-placeholder" x-text="placeholder"></span>
                    </template>
                    <template x-for="val in selected" :key="val">
                        <span class="vio-ms-chip">
                            <span x-text="labelFor(val)"></span>
                            <button type="button" class="vio-ms-chip-x" @click.stop="toggle(val)">×</button>
                        </span>
                    </template>
                    <span class="vio-ms-caret">▾</span>
                </button>
                <div class="vio-ms-panel" x-show="open" x-cloak>
                    <input type="search" class="vio-ms-search" x-ref="q" x-model="query" placeholder="Search file…" @keydown.escape.stop="open = false" />
                    <ul class="vio-ms-list">
                        <template x-for="opt in filtered" :key="opt.value">
                            <li>
                                <button type="button" class="vio-ms-option" :class="{ 'is-on': selected.includes(opt.value) }" @click="toggle(opt.value)">
                                    <span x-text="opt.label"></span>
                                    <span class="vio-ms-check" x-show="selected.includes(opt.value)">✓</span>
                                </button>
                            </li>
                        </template>
                        <li x-show="filtered.length === 0" class="vio-ms-empty">No matches</li>
                    </ul>
                </div>
            </div>
        </div>
        @if ($isAwaitingTab)
            <div class="block min-w-[14rem] text-sm">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</span>
                <div
                    class="vio-ms mt-1.5"
                    wire:key="status-filter-{{ md5(json_encode($statusFilterValues)) }}"
                    x-data="vioMultiSelect({
                        options: {{ $statusOptionsJson }},
                        selected: @js($statusFilterValues),
                        placeholder: 'Any status',
                        onChange(values) { $wire.set('statusFilters', values) }
                    })"
                    @click.outside="open = false"
                >
                    <button type="button" class="vio-ms-control" @click="open = !open; if (open) $nextTick(() => $refs.q.focus())">
                        <template x-if="selected.length === 0">
                            <span class="vio-ms-placeholder" x-text="placeholder"></span>
                        </template>
                        <template x-for="val in selected" :key="val">
                            <span class="vio-ms-chip">
                                <span x-text="labelFor(val)"></span>
                                <button type="button" class="vio-ms-chip-x" @click.stop="toggle(val)">×</button>
                            </span>
                        </template>
                        <span class="vio-ms-caret">▾</span>
                    </button>
                    <div class="vio-ms-panel" x-show="open" x-cloak>
                        <input type="search" class="vio-ms-search" x-ref="q" x-model="query" placeholder="Search…" @keydown.escape.stop="open = false" />
                        <ul class="vio-ms-list">
                            <template x-for="opt in filtered" :key="opt.value">
                                <li>
                                    <button type="button" class="vio-ms-option" :class="{ 'is-on': selected.includes(opt.value) }" @click="toggle(opt.value)">
                                        <span x-text="opt.label"></span>
                                        <span class="vio-ms-check" x-show="selected.includes(opt.value)">✓</span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="filtered.length === 0" class="vio-ms-empty">No matches</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
        @if ($filtersActive)
            <x-filament::button size="sm" color="gray" wire:click="clearFilters">Clear filters</x-filament::button>
        @endif
        <div class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-1 pb-1 text-xs text-slate-500">
            <span>
                Showing <strong class="text-slate-800">{{ count($this->logs) }}</strong>
                <span class="text-slate-400">· {{ $isAwaitingTab ? 'awaiting' : 'imported' }}</span>
            </span>
        </div>
    </div>

    @if (count($this->logs) === 0)
        <p class="text-sm text-gray-500">
            @if ($isAwaitingTab)
                Nothing awaiting import.
                @if ($filtersActive)
                    Try clearing filters, or check the <button type="button" class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-2" wire:click="setListTab('imported')">Imported</button> tab.
                @else
                    On <strong>Read emails</strong> use Fetch + Find driver first.
                @endif
            @else
                No imported fines yet.
                After <strong>Start import</strong>, confirm on <strong>Imported</strong> with Update Wheelsys status.
                @if ($filtersActive)
                    Or clear the Import file filter.
                @endif
            @endif
        </p>
    @else
        <div style="margin-bottom: 5.5rem;">
            @if (! $isAwaitingTab)
                @foreach ($this->logDayGroups as $day)
                    @php
                        $dayKey = (string) $day['key'];
                        $dayOpen = $this->isBatchOpen($dayKey);
                    @endphp
                    <div
                        wire:key="acc-{{ $dayKey }}"
                        @class(['vio-acc-day', 'vio-acc-open' => $dayOpen])
                    >
                        <button
                            type="button"
                            class="vio-acc-head"
                            wire:click="toggleBatch(@js($dayKey))"
                        >
                            <svg class="vio-acc-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                            </svg>
                            <div class="min-w-0">
                                <div class="vio-acc-title truncate">{{ $day['title'] }}</div>
                                <div class="vio-acc-sub truncate">{{ $day['subtitle'] }}</div>
                            </div>
                            @if (! empty($day['imported_at_label']))
                                <span class="vio-acc-imported-when" title="Imported">{{ $day['imported_at_label'] }}</span>
                            @endif
                            <span class="vio-acc-status vio-acc-status-imported">Imported</span>
                            <span class="vio-acc-count">{{ $day['fine_count'] }}</span>
                        </button>

                        @if ($dayOpen)
                            <div class="vio-acc-day-body">
                                @foreach ($day['files'] as $group)
                                    @include('filament.pages.partials.wheels-import-file-group', [
                                        'group' => $group,
                                        'nested' => true,
                                    ])
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                @foreach ($this->logGroups as $group)
                    @include('filament.pages.partials.wheels-import-file-group', [
                        'group' => $group,
                        'nested' => false,
                    ])
                @endforeach
            @endif
        </div>

        <div class="vio-footer-bar fixed inset-x-0 bottom-0">
            <div class="flex max-w-full flex-wrap items-center gap-2 px-3 py-3 lg:px-4">
                <p class="mr-auto text-xs text-slate-500">
                    Selected <strong class="text-slate-800">{{ count($selectedIds) }}</strong>
                </p>
                <x-filament::button size="sm" color="gray" wire:click="toggleSelectAll">All / None</x-filament::button>
                <span class="hidden h-5 w-px bg-slate-200 sm:block"></span>
                <x-filament::button
                    size="sm"
                    color="success"
                    wire:click="startImport"
                    wire:loading.attr="disabled"
                    wire:target="startImport"
                >
                    <span wire:loading.remove wire:target="startImport">Start import</span>
                    <span wire:loading wire:target="startImport">Preparing…</span>
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
