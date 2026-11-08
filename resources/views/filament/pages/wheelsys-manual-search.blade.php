<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        One plate · date range (default last 6 months) · then <strong>Find fines</strong> or <strong>Find contracts</strong>.
        Open <strong>Details</strong> for the full record.
    </div>

    <form class="space-y-4" wire:submit.prevent>
        {{ $this->form }}

        <div class="flex flex-wrap gap-2">
            <x-filament::button
                type="button"
                color="primary"
                icon="heroicon-o-exclamation-triangle"
                wire:click="findFines"
                wire:loading.attr="disabled"
                wire:target="findFines,findContracts"
            >
                <span wire:loading.remove wire:target="findFines">Find fines</span>
                <span wire:loading wire:target="findFines">Searching…</span>
            </x-filament::button>
            <x-filament::button
                type="button"
                color="success"
                icon="heroicon-o-document-text"
                wire:click="findContracts"
                wire:loading.attr="disabled"
                wire:target="findFines,findContracts"
            >
                <span wire:loading.remove wire:target="findContracts">Find contracts</span>
                <span wire:loading wire:target="findContracts">Searching…</span>
            </x-filament::button>
        </div>
    </form>

    @if ($resultMode && count($results) > 0)
        <div class="mt-8 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <div class="border-b border-gray-100 bg-gray-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                {{ $resultMode === 'fines' ? 'Fines (traffic violations)' : 'Contracts (rentals)' }}
                · {{ count($results) }}
            </div>
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-white text-xs uppercase text-gray-500">
                    @if ($resultMode === 'fines')
                        <tr>
                            <th class="px-3 py-2">Vehicle</th>
                            <th class="px-3 py-2">Ticket date</th>
                            <th class="px-3 py-2">Reference</th>
                            <th class="px-3 py-2">Driver</th>
                            <th class="px-3 py-2">RNT</th>
                            <th class="px-3 py-2">Authority</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    @else
                        <tr>
                            <th class="px-3 py-2">Vehicle</th>
                            <th class="px-3 py-2">From</th>
                            <th class="px-3 py-2">To</th>
                            <th class="px-3 py-2">RNT</th>
                            <th class="px-3 py-2">Driver</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    @endif
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($results as $index => $row)
                        <tr class="hover:bg-gray-50" wire:key="ms-{{ $resultMode }}-{{ $index }}">
                            @if ($resultMode === 'fines')
                                <td class="px-3 py-2 font-medium">{{ $row['vehicle'] ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['ticket_date'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['ticket_number'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['driver'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['agr_no'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['authority_name'] ?? $row['authority_email'] ?? '—' }}</td>
                            @else
                                <td class="px-3 py-2 font-medium">{{ $row['vehicle'] ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ substr((string) ($row['date_from'] ?? ''), 0, 10) ?: '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ substr((string) ($row['date_to'] ?? ''), 0, 10) ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $row['agr_no'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['driver'] ?? $row['corporate'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['customer_email'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['status'] ?? '—' }}</td>
                            @endif
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <div class="inline-flex flex-wrap justify-end gap-1.5">
                                    <x-filament::button size="sm" color="gray" wire:click="openDetails({{ $index }})">
                                        Details
                                    </x-filament::button>
                                    <x-filament::button
                                        size="sm"
                                        color="primary"
                                        icon="heroicon-o-arrow-top-right-on-square"
                                        wire:click="createFromResult({{ $index }})"
                                    >
                                        Create in Wheels
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($resultMode)
        <p class="mt-6 text-sm text-gray-500">No results in this date range.</p>
    @endif

    @if ($showDetails && $detailsRow)
        <div
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto"
            style="background: rgba(15, 23, 42, 0.5); padding: 24px;"
            wire:click.self="closeDetails"
        >
            <div
                class="w-full rounded-xl bg-white shadow-xl"
                style="max-width: 44rem; margin: 16px 0; border: 1px solid rgba(15,23,42,.08);"
            >
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950" style="margin: 0;">
                            {{ $resultMode === 'contracts' ? 'Contract details' : 'Fine details' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $detailsRow['vehicle'] ?? '—' }}
                            @if (filled($detailsRow['agr_no'] ?? null))
                                · {{ $detailsRow['agr_no'] }}
                            @endif
                            @if (filled($detailsRow['ticket_number'] ?? null))
                                · {{ $detailsRow['ticket_number'] }}
                            @endif
                        </p>
                    </div>
                    <x-filament::button size="sm" color="gray" wire:click="closeDetails">Close</x-filament::button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        @foreach ($this->detailsFields as $field)
                            <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-100">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
                                <dd class="mt-0.5 break-words text-slate-900">{{ $field['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if (! empty($detailsRow['raw']) && is_array($detailsRow['raw']))
                        <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Raw Wheelsys fields
                            </summary>
                            <pre class="mt-2 overflow-auto text-[11px] leading-relaxed text-slate-700" style="white-space: pre-wrap; word-break: break-word;">{{ json_encode($detailsRow['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
                    <x-filament::button color="gray" wire:click="closeDetails">Close</x-filament::button>
                    <x-filament::button color="primary" wire:click="useDetailsForCreate">
                        Create in Wheels
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
