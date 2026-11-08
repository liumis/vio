<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200" style="padding: 14px 16px;">
            <p class="text-sm text-amber-900" style="margin: 0;">
                Instrukcija: periodiškai tikrinti <strong>Atmestus</strong> pranešimus IRD ePaslaugose.
                Po „Mark ePaslaugos done“ įrašai atsiranda čia. Portalas API neturi — atidarote ePaslaugas ir pažymite rezultatą.
            </p>
            <div style="margin-top: 10px;">
                <x-filament::button
                    tag="a"
                    size="sm"
                    color="warning"
                    href="{{ \App\Support\EpaslaugosAssist::PORTAL_URL }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Open ePaslaugos
                </x-filament::button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <table class="w-full text-sm" style="border-collapse: collapse;">
                <thead>
                    <tr class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <th style="padding: 10px 12px;">#</th>
                        <th style="padding: 10px 12px;">ROIK / ref</th>
                        <th style="padding: 10px 12px;">Vehicle</th>
                        <th style="padding: 10px 12px;">Driver</th>
                        <th style="padding: 10px 12px;">Submitted</th>
                        <th style="padding: 10px 12px;">Atmesti</th>
                        <th style="padding: 10px 12px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr style="border-top: 1px solid #eef2f7; {{ ! empty($row['due']) ? 'background:#fff7ed;' : '' }}">
                            <td style="padding: 10px 12px;">{{ $row['id'] }}</td>
                            <td style="padding: 10px 12px; font-weight: 600;">{{ $row['reference'] ?: '—' }}</td>
                            <td style="padding: 10px 12px;">{{ $row['vehicle'] ?: '—' }}</td>
                            <td style="padding: 10px 12px;">{{ $row['driver'] ?: '—' }}</td>
                            <td style="padding: 10px 12px;">{{ $row['submitted_at'] ?: '—' }}</td>
                            <td style="padding: 10px 12px;">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $row['atmesti_status'] === 'clear' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $row['atmesti_status'] === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $row['atmesti_status'] === 'pending' ? 'bg-amber-100 text-amber-900' : '' }}
                                ">
                                    {{ $row['atmesti_status'] }}
                                </span>
                                @if ($row['checked_at'])
                                    <div class="text-xs text-slate-500">checked {{ $row['checked_at'] }}</div>
                                @endif
                                @if ($row['note'])
                                    <div class="text-xs text-slate-500">{{ $row['note'] }}</div>
                                @endif
                            </td>
                            <td style="padding: 10px 12px;">
                                <div class="flex flex-wrap gap-1">
                                    <x-filament::button size="xs" color="success" wire:click="markClear({{ $row['id'] }})">
                                        Clear
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="danger" wire:click="markRejected({{ $row['id'] }})">
                                        Rejected
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" wire:click="markRecheckLater({{ $row['id'] }})">
                                        Rechecked
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 24px 12px; color: #64748b;">
                                No ePaslaugos submissions awaiting Atmesti follow-up yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
