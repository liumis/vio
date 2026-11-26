@php
    /** @var array<string, mixed> $group */
    $groupKey = (string) $group['key'];
    $isOpen = $this->isBatchOpen($groupKey);
    $groupLogs = $group['logs'] ?? [];
    $nested = ! empty($nested);
@endphp
<div
    wire:key="acc-{{ $groupKey }}"
    @class([
        'vio-acc',
        'vio-acc-nested' => $nested,
        'vio-acc-new' => ! empty($group['is_new']),
        'vio-acc-open' => $isOpen,
    ])
>
    <button
        type="button"
        class="vio-acc-head"
        wire:click="toggleBatch(@js($groupKey))"
    >
        <svg class="vio-acc-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
        </svg>
        <div class="min-w-0">
            <div class="vio-acc-title truncate">{{ $group['title'] }}</div>
            @if (! empty($group['subtitle']))
                <div class="vio-acc-sub truncate">{{ $group['subtitle'] }}</div>
            @endif
        </div>
        @if (! empty($group['imported_at_label']))
            <span class="vio-acc-imported-when" title="Imported date">
                {{ $group['imported_at_label'] }}
            </span>
        @endif
        @if (! empty($group['import_label']))
            <span class="vio-acc-status vio-acc-status-{{ $group['import_status'] ?? 'not_imported' }}">
                {{ ($group['import_status'] ?? '') === 'imported' ? 'Imported' : $group['import_label'] }}
            </span>
        @endif
        <span class="vio-acc-count">{{ count($groupLogs) }}</span>
    </button>

    @if ($isOpen)
        <div class="vio-acc-body">
            @php
                $selectableInGroup = 0;
                foreach ($groupLogs as $groupLog) {
                    if ($this->rowIsSelectable($groupLog)) {
                        $selectableInGroup++;
                    }
                }
            @endphp
            <div class="vio-acc-actions">
                @if (! empty($group['is_new']))
                    @if ($selectableInGroup > 0)
                        <button
                            type="button"
                            wire:click="selectGroup('{{ $groupKey }}')"
                            class="inline-flex items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm"
                            style="background:#16a34a; border:0; cursor:pointer;"
                        >
                            Select all ready
                        </button>
                    @endif
                @else
                    @if (($group['import_status'] ?? '') !== 'imported')
                        <button
                            type="button"
                            wire:click="importPreparedBatch({{ \Illuminate\Support\Js::from($groupKey) }})"
                            wire:loading.attr="disabled"
                            wire:target="importPreparedBatch"
                            class="inline-flex items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm"
                            style="background:#16a34a; border:0; cursor:pointer;"
                        >
                            <span wire:loading.remove wire:target="importPreparedBatch">Download &amp; open Wheels</span>
                            <span wire:loading wire:target="importPreparedBatch">Working…</span>
                        </button>
                        <button
                            type="button"
                            wire:click="updateWheelsysStatus({{ \Illuminate\Support\Js::from($groupKey) }})"
                            wire:loading.attr="disabled"
                            wire:target="updateWheelsysStatus"
                            class="inline-flex items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-900 shadow-sm"
                            style="background:#fef3c7; border:1px solid #fbbf24; cursor:pointer;"
                        >
                            <span wire:loading.remove wire:target="updateWheelsysStatus">Update status</span>
                            <span wire:loading wire:target="updateWheelsysStatus">Updating…</span>
                        </button>
                    @endif
                    @if (! empty($group['sharepoint_url']))
                        <x-filament::button
                            tag="a"
                            size="xs"
                            color="gray"
                            :href="$group['sharepoint_url']"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Open file
                        </x-filament::button>
                    @endif
                    @if ($selectableInGroup > 0)
                        <button
                            type="button"
                            wire:click="selectGroup('{{ $groupKey }}')"
                            class="inline-flex items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200"
                            style="background:#fff; cursor:pointer;"
                        >
                            Select ready in file
                        </button>
                    @endif
                @endif
            </div>

            @if ($groupLogs === [])
                <p class="text-sm text-slate-500" style="margin: 0; padding: 16px;">No fines in this group.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="vio-fines-table w-full min-w-[900px] text-left">
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groupLogs as $log)
                                @php
                                    $selectable = $this->rowIsSelectable($log);
                                    $status = $this->rowStatusLabel($log);
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
                                            @if ($log->wheels_imported_at)
                                                <div style="margin-top: 3px; font-size: 11px; font-weight: 800; color: #047857; font-variant-numeric: tabular-nums;">
                                                    {{ $log->wheels_imported_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                                </div>
                                            @endif
                                        @elseif ($status === 'Missing in Wheels')
                                            <span class="vio-chip vio-chip-warn">Missing</span>
                                        @else
                                            <span class="vio-chip vio-chip-sky">In import file</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
