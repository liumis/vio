@if ($showInformuoti && $previewDraft)
    <div
        class="fixed inset-0 flex items-start justify-center overflow-y-auto"
        style="z-index: 100; background: rgba(15, 23, 42, 0.5); padding: 24px;"
        wire:click.self="closeInformuoti"
    >
        <div
            class="w-full rounded-xl bg-white shadow-xl"
            style="max-width: 64rem; margin: 16px 0; box-shadow: 0 20px 40px rgba(15,23,42,.18); border: 1px solid rgba(15,23,42,.08);"
        >
            <div class="flex items-start justify-between gap-4" style="padding: 24px 28px; border-bottom: 1px solid #eef2f7;">
                <div style="padding-right: 12px;">
                    <h2 class="text-lg font-semibold text-gray-950" style="margin: 0;">
                        Preview / Send mail
                        @if (($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                            · ANR / ePaslaugos
                        @endif
                    </h2>
                    <p class="text-sm text-gray-500" style="margin: 8px 0 0;">
                        {{ $previewDraft['authority_name'] ?? '—' }}
                    </p>
                </div>
                <x-filament::button size="sm" color="gray" wire:click="closeInformuoti">Close</x-filament::button>
            </div>

            @php
                $readiness = is_array($informuotiReadiness ?? null) ? $informuotiReadiness : null;
                $missingLabels = is_array($readiness['missing_labels'] ?? null) ? $readiness['missing_labels'] : [];
                $severity = (string) ($readiness['severity'] ?? 'ok');
                $alreadySent = ! empty($readiness['sent']);
            @endphp

            @if ($alreadySent)
                <div style="padding: 12px 28px; border-bottom: 1px solid #bae6fd; background: #e0f2fe; color: #075985;">
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <span style="font-size:16px;line-height:1.2;" aria-hidden="true">ℹ</span>
                        <div>
                            <div style="font-size:13px;font-weight:700;">Already sent</div>
                            <div style="font-size:12px;margin-top:2px;">{{ $readiness['detail'] ?? 'Authority reply already sent.' }}</div>
                        </div>
                    </div>
                </div>
            @elseif ($missingLabels !== [])
                <div
                    style="padding: 12px 28px; border-bottom: 1px solid {{ $severity === 'error' ? '#fecaca' : '#fde68a' }}; background: {{ $severity === 'error' ? '#fef2f2' : '#fffbeb' }}; color: {{ $severity === 'error' ? '#991b1b' : '#92400e' }};"
                >
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <span style="font-size:16px;line-height:1.2;" aria-hidden="true">{{ $severity === 'error' ? '⚠' : '⚠' }}</span>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:13px;font-weight:700;">
                                {{ $severity === 'error' ? 'Missing required data' : 'Incomplete data' }}
                            </div>
                            <div style="font-size:12px;margin-top:4px;">
                                Fill or pick a Wheelsys rental before sending.
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                                @foreach ($missingLabels as $label)
                                    <span
                                        style="display:inline-flex;align-items:center;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;background:{{ $severity === 'error' ? '#fee2e2' : '#fef3c7' }};color:{{ $severity === 'error' ? '#b91c1c' : '#b45309' }};border:1px solid {{ $severity === 'error' ? '#fca5a5' : '#fcd34d' }};"
                                    >{{ $label }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (($sourceMailHtml ?? '') !== '' || ($sourceFiles ?? []) !== [] || ($sourceExtractText ?? '') !== '')
                <div style="padding: 16px 28px; border-bottom: 1px solid #eef2f7; background: #fff7ed;">
                    <p class="text-xs font-semibold uppercase text-amber-800" style="margin: 0 0 10px;">
                        Original email &amp; attachments
                    </p>

                    @if (($sourceFiles ?? []) !== [])
                        <div class="flex flex-wrap gap-2" style="margin-bottom: 12px;">
                            @foreach ($sourceFiles as $file)
                                <div class="rounded-lg bg-white text-xs text-slate-800 ring-1 ring-amber-200" style="padding: 6px 10px;">
                                    {{ \Illuminate\Support\Str::limit($file['name'] ?? 'file', 40) }}
                                    <span class="text-slate-500">
                                        · {{ $file['kind'] ?? 'file' }}
                                        @if (($file['bytes'] ?? 0) > 0)
                                            · {{ number_format(($file['bytes'] ?? 0) / 1024, 0) }} KB
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (($sourceMailHtml ?? '') !== '')
                        <iframe
                            wire:ignore
                            title="Inbound email"
                            sandbox=""
                            srcdoc="{!! htmlspecialchars($sourceMailHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') !!}"
                            style="width:100%;height:240px;border:1px solid #fdba74;border-radius:8px;background:#fff;"
                        ></iframe>
                    @endif

                    @if (($sourceExtractText ?? '') !== '')
                        <details style="margin-top: 12px;">
                            <summary class="cursor-pointer text-xs font-semibold text-amber-900">Extracted / OCR text</summary>
                            <pre class="mt-2 overflow-auto rounded-lg bg-white text-xs text-slate-800 ring-1 ring-amber-200" style="max-height:160px;padding:12px;white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($sourceExtractText, 8000) }}</pre>
                        </details>
                    @endif
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
                    <div class="text-xs font-semibold uppercase text-gray-500">Ticket date / time</div>
                    <div class="mt-1 text-sm font-medium text-gray-900">
                        @php
                            $ticketMoment = trim((string) (($previewDraft['ticket_date_hint'] ?? '').' '.($previewDraft['ticket_time_hint'] ?? '')));
                        @endphp
                        {{ $ticketMoment !== ''
                            ? \App\Support\WheelsysClient::formatDateTimeLabel(
                                ($previewDraft['ticket_date_hint'] ?? '').(
                                    filled($previewDraft['ticket_time_hint'] ?? null)
                                        ? ' '.$previewDraft['ticket_time_hint']
                                        : ''
                                )
                            )
                            : '—' }}
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2" style="padding: 24px 28px; border-bottom: 1px solid #eef2f7;">
                <div class="space-y-4 text-sm">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-gray-500">To</span>
                        <input
                            type="email"
                            wire:model="previewDraft.to"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm"
                            style="padding: 10px 12px;"
                            @disabled(($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                        />
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-gray-500">Subject</span>
                        <input
                            type="text"
                            wire:model.blur="previewDraft.subject"
                            wire:change="refreshPreviewHtml"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm"
                            style="padding: 10px 12px;"
                        />
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
                            type="datetime-local"
                            wire:model="searchTicketDate"
                            step="60"
                            class="rounded-lg border-gray-300 text-sm shadow-sm"
                            style="padding: 10px 12px;"
                        />
                        <x-filament::button size="sm" color="gray" wire:click="clearSearchTicketDate">Reset date</x-filament::button>
                        <x-filament::button size="sm" wire:click="searchWheelsys" wire:loading.attr="disabled">Search</x-filament::button>
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
                                                $dateLabel = \App\Support\WheelsysClient::formatRentalWindow(
                                                    $wrow['date_from'] ?? null,
                                                    $wrow['date_to'] ?? null,
                                                ) ?: '—';
                                            } elseif (filled($wrow['ticket_datetime'] ?? null)) {
                                                $dateLabel = \App\Support\WheelsysClient::formatDateTimeLabel($wrow['ticket_datetime']);
                                            } elseif (filled($wrow['ticket_date'] ?? null)) {
                                                $dateLabel = \App\Support\WheelsysClient::formatDateTimeLabel($wrow['ticket_date']);
                                            }
                                        @endphp
                                        <tr
                                            wire:key="imp-ws-{{ $wi }}"
                                            style="cursor:pointer;{{ (int) $wheelsysSelectedIndex === (int) $wi ? 'background:#eef6ff;' : '' }}"
                                            wire:click="$set('wheelsysSelectedIndex', {{ $wi }})"
                                        >
                                            <td style="padding: 8px 10px;">
                                                <input type="radio" wire:model.live="wheelsysSelectedIndex" value="{{ $wi }}" />
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
                    <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0;">Reply draft (HTML)</p>
                    @if (! $showTemplateEditor)
                        <x-filament::button size="sm" color="gray" wire:click="openTemplateEditor">Edit text</x-filament::button>
                    @else
                        <x-filament::button size="sm" color="gray" wire:click="closeTemplateEditor">Done editing</x-filament::button>
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
                <x-filament::button color="gray" wire:click="closeInformuoti">Close</x-filament::button>
                <x-filament::button color="gray" wire:click="createInWheelsys">Create in Wheels</x-filament::button>
                <x-filament::button color="gray" wire:click="markWheelsCreated">Mark Wheels done</x-filament::button>
                @if (($previewDraft['reply_channel'] ?? '') === 'epaslaugos')
                    <x-filament::button color="warning" wire:click="markEpaslaugosDone">Mark ePaslaugos done</x-filament::button>
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
