@if ($showInformuoti && $previewDraft)
    @php
        $readiness = is_array($informuotiReadiness ?? null) ? $informuotiReadiness : null;
        $missingLabels = is_array($readiness['missing_labels'] ?? null) ? $readiness['missing_labels'] : [];
        $severity = (string) ($readiness['severity'] ?? 'ok');
        $alreadySent = ! empty($readiness['sent']);
        $requiresContract = ! empty($previewDraft['requires_contract']);
        $agrNo = trim((string) (($previewDraft['agr_no'] ?? '') ?: ($wheelsysSelected['agr_no'] ?? '') ?: ''));
        $isEpaslaugos = ($previewDraft['reply_channel'] ?? '') === 'epaslaugos';
        $defaultTab = $isEpaslaugos || $alreadySent || $missingLabels !== [] ? 'compose' : 'mail';
        $inboundFrom = trim((string) (($previewMessage['from_name'] ?? '').' <'.($previewMessage['from_email'] ?? '').'>'));
        $inboundSubject = (string) ($previewMessage['subject'] ?? '');
        $inboundReceived = (string) ($previewMessage['received_at_local'] ?? $previewMessage['received_at'] ?? '');
        $externalCompany = is_array($wheelsysSelected['_external_company'] ?? null)
            ? $wheelsysSelected['_external_company']
            : null;
        $externalCompanyFilled = is_array($externalCompany)
            && ($externalCompany['status'] ?? '') === 'filled'
            && ($externalCompany['filled'] ?? []) !== [];
        $hasRentalId = filled($wheelsysSelected['id'] ?? null)
            || filled($wheelsysSelected['rental_id'] ?? null)
            || filled($informuotiRentalId ?? null);
    @endphp
    <div
        class="fixed inset-0 flex items-start justify-center overflow-y-auto"
        style="z-index: 100; background: rgba(15, 23, 42, 0.5); padding: 24px;"
        wire:key="informuoti-overlay-{{ $informuotiLogId ?? 'x' }}"
        wire:click.self="closeInformuoti"
        x-data="{ tab: @js($defaultTab) }"
    >
        <style>
            .vio-im-tabs {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 4px;
                width: max-content;
                max-width: 100%;
                padding: 4px;
                border-radius: 12px;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
            }
            .vio-im-tab {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                position: relative;
                z-index: 1;
                border: 0;
                margin: 0;
                background: transparent;
                color: #64748b;
                cursor: pointer;
                border-radius: 9px;
                padding: 8px 14px;
                font-size: 13px;
                font-weight: 650;
                line-height: 1.2;
                white-space: nowrap;
            }
            .vio-im-tab.is-active {
                background: #fff;
                color: #0f172a;
                box-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 0 0 1px rgba(226, 232, 240, .9);
            }
            .vio-im-tab-badge {
                display: inline-flex;
                align-items: center;
                border-radius: 999px;
                padding: 2px 7px;
                font-size: 10px;
                font-weight: 800;
                line-height: 1.2;
            }
            .vio-im-tab-badge.warn { background: #fef3c7; color: #92400e; }
            .vio-im-tab-badge.danger { background: #fee2e2; color: #b91c1c; }
            .vio-im-meta {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px 16px;
                padding: 14px 24px;
                border-bottom: 1px solid #eef2f7;
                background: #f8fafc;
            }
            .vio-im-compose-stack {
                display: flex;
                flex-direction: column;
                gap: 16px;
                padding: 16px 24px;
                border-bottom: 1px solid #eef2f7;
            }
            @media (max-width: 720px) {
                .vio-im-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            [x-cloak] { display: none !important; }
        </style>
        <div
            class="w-full rounded-xl bg-white shadow-xl"
            style="max-width: 56rem; margin: 16px 0; box-shadow: 0 20px 40px rgba(15,23,42,.18); border: 1px solid rgba(15,23,42,.08);"
            wire:click.stop
            wire:keydown.enter.stop
            x-on:click.stop
        >
            <div class="flex items-start justify-between gap-4" style="padding: 20px 24px 12px; border-bottom: 1px solid #eef2f7;">
                <div style="padding-right: 12px; min-width: 0;">
                    <h2 class="text-lg font-semibold text-gray-950" style="margin: 0;">
                        Preview / Send
                        @if ($isEpaslaugos)
                            · ANR / ePaslaugos
                        @endif
                    </h2>
                    <p class="text-sm text-gray-500 truncate" style="margin: 6px 0 0;">
                        {{ $previewDraft['authority_name'] ?? '—' }}
                        @if (($previewDraft['vehicle_hint'] ?? '') !== '')
                            · {{ $previewDraft['vehicle_hint'] }}
                        @endif
                    </p>
                </div>
                <x-filament::button size="sm" color="gray" wire:click="closeInformuoti">Close</x-filament::button>
            </div>

            <div style="padding: 12px 24px 14px;">
                <div class="vio-im-tabs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        class="vio-im-tab"
                        :class="{ 'is-active': tab === 'mail' }"
                        :aria-selected="tab === 'mail'"
                        x-on:click="tab = 'mail'"
                    >
                        Received mail
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="vio-im-tab"
                        :class="{ 'is-active': tab === 'compose' }"
                        :aria-selected="tab === 'compose'"
                        x-on:click="tab = 'compose'"
                    >
                        Compose reply
                        @if ($missingLabels !== [] && ! $alreadySent)
                            <span class="vio-im-tab-badge danger">!</span>
                        @elseif ($isEpaslaugos)
                            <span class="vio-im-tab-badge warn">ANR</span>
                        @endif
                    </button>
                </div>
            </div>

            {{-- Tab 1: received mail --}}
            <div x-show="tab === 'mail'" x-cloak style="padding: 16px 24px 8px;">
                <div class="grid gap-3 sm:grid-cols-3" style="margin-bottom: 14px;">
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">From</div>
                        <div class="mt-1 text-sm text-gray-900 break-all">
                            {{ $inboundFrom !== '<>' && $inboundFrom !== '' ? $inboundFrom : (($previewDraft['authority_name'] ?? '') ?: '—') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Subject</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $inboundSubject !== '' ? $inboundSubject : '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase text-gray-500">Received</div>
                        <div class="mt-1 text-sm text-gray-900">{{ $inboundReceived !== '' ? $inboundReceived : '—' }}</div>
                    </div>
                </div>

                @if (($sourceFiles ?? []) !== [])
                    <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0 0 8px;">Attachments</p>
                    <div class="flex flex-wrap gap-2" style="margin-bottom: 14px;">
                        @foreach ($sourceFiles as $file)
                            <div class="rounded-lg bg-slate-50 text-xs text-slate-800 ring-1 ring-slate-200" style="padding: 6px 10px;">
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
                        style="width:100%;height:min(52vh,420px);border:1px solid #e2e8f0;border-radius:10px;background:#fff;"
                    ></iframe>
                @else
                    <p class="text-sm text-gray-500" style="margin: 12px 0;">No original HTML body stored for this fine.</p>
                @endif

                @if (($sourceExtractText ?? '') !== '')
                    <details style="margin-top: 12px;">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-700">Extracted / OCR text</summary>
                        <pre class="mt-2 overflow-auto rounded-lg bg-slate-50 text-xs text-slate-800 ring-1 ring-slate-200" style="max-height:160px;padding:12px;white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($sourceExtractText, 8000) }}</pre>
                    </details>
                @endif

                <div style="padding: 16px 0 8px; display:flex; justify-content:flex-end;">
                    <x-filament::button size="sm" color="primary" x-on:click="tab = 'compose'">
                        Continue to compose →
                    </x-filament::button>
                </div>
            </div>

            {{-- Tab 2: compose reply --}}
            <div x-show="tab === 'compose'" x-cloak>
                @if ($isEpaslaugos)
                    <p class="text-sm text-amber-700" style="margin: 0; padding: 12px 24px; background:#fffbeb; border-bottom:1px solid #fde68a;">
                        ANR/ROIK: ne el. laišku — duomenis teikti per <strong>IRD ePaslaugas</strong>.
                    </p>
                @endif

                @include('filament.resources.violation-resource.informuoti-modal-epaslaugos')

                @if ($externalCompanyFilled)
                    <div style="padding: 12px 24px; border-bottom: 1px solid #fcd34d; background: #fffbeb; color: #92400e;">
                        <div style="font-size:13px;font-weight:700;">Filled from external source</div>
                        <div style="font-size:12px;margin-top:2px;">
                            {{ implode(' + ', array_map(static fn ($f) => $f === 'company_code' ? 'company code' : 'company name', $externalCompany['filled'])) }}
                            · {{ $externalCompany['display_name'] ?? $externalCompany['name'] ?? '—' }}
                            @if (! empty($externalCompany['code']))
                                · {{ $externalCompany['code'] }}
                            @endif
                            ({{ $externalCompany['source'] ?? 'rekvizitai.lt / JAR' }})
                            @if (! empty($externalCompany['rekvizitai_url']))
                                · <a href="{{ $externalCompany['rekvizitai_url'] }}" target="_blank" rel="noopener" style="color:#b45309;text-decoration:underline;">rekvizitai.lt</a>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($alreadySent)
                    <div style="padding: 12px 24px; border-bottom: 1px solid #bae6fd; background: #e0f2fe; color: #075985;">
                        <div style="font-size:13px;font-weight:700;">Already sent</div>
                        <div style="font-size:12px;margin-top:2px;">{{ $readiness['detail'] ?? 'Authority reply already sent.' }}</div>
                    </div>
                @elseif ($missingLabels !== [])
                    <div
                        style="padding: 12px 24px; border-bottom: 1px solid {{ $severity === 'error' ? '#fecaca' : '#fde68a' }}; background: {{ $severity === 'error' ? '#fef2f2' : '#fffbeb' }}; color: {{ $severity === 'error' ? '#991b1b' : '#92400e' }};"
                    >
                        <div style="font-size:13px;font-weight:700;">
                            {{ $severity === 'error' ? 'Missing required data' : 'Incomplete data' }}
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                            @foreach ($missingLabels as $label)
                                <span
                                    style="display:inline-flex;align-items:center;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;background:{{ $severity === 'error' ? '#fee2e2' : '#fef3c7' }};color:{{ $severity === 'error' ? '#b91c1c' : '#b45309' }};border:1px solid {{ $severity === 'error' ? '#fca5a5' : '#fcd34d' }};"
                                >{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="vio-im-meta">
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
                    <div>
                        <div class="text-xs font-semibold uppercase" style="color: {{ $requiresContract && $agrNo === '' ? '#b91c1c' : '#64748b' }};">
                            Contract (RNT)@if ($requiresContract) * @endif
                        </div>
                        <div
                            class="mt-1 text-sm font-medium"
                            style="color: {{ $requiresContract && $agrNo === '' ? '#b91c1c' : '#0f172a' }}; font-weight: {{ $requiresContract ? 800 : 500 }};"
                        >
                            {{ $agrNo !== '' ? $agrNo : ($requiresContract ? 'Missing — pick rental' : '—') }}
                        </div>
                    </div>
                </div>

                <div class="vio-im-compose-stack">
                    <div class="space-y-3 text-sm">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-gray-500">To</span>
                            <input
                                type="text"
                                inputmode="email"
                                autocomplete="email"
                                wire:model.blur="previewDraft.to"
                                wire:keydown.enter.prevent
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm"
                                style="padding: 10px 12px;"
                                @disabled($isEpaslaugos)
                            />
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-gray-500">Subject</span>
                            <input
                                type="text"
                                wire:model.blur="previewDraft.subject"
                                wire:change="refreshPreviewHtml"
                                wire:keydown.enter.prevent
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm"
                                style="padding: 10px 12px;"
                            />
                        </label>
                        <div class="text-xs text-gray-500" style="line-height: 1.5;">
                            From: {{ $previewDraft['from_name'] ?? '' }} &lt;{{ $previewDraft['from_email'] ?? '' }}&gt;
                            @if (! empty($previewDraft['reply_to']))
                                · Reply-To: {{ $previewDraft['reply_to'] }}
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200" style="padding: 14px;">
                        <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0 0 10px;">Wheelsys fill</p>
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
                            <div class="mt-3 max-h-36 overflow-auto rounded-lg bg-white ring-1 ring-gray-200">
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
                            <p class="text-xs text-emerald-700" style="margin: 10px 0 0;">
                                Selected:
                                {{ $wheelsysSelected['vehicle'] ?? '' }}
                                · {{ $wheelsysSelected['driver'] ?? '' }}
                                · {{ $wheelsysSelected['birth_date'] ?? '—' }}
                                @if (! empty($wheelsysSelected['agr_no']))
                                    · <strong>RNT {{ $wheelsysSelected['agr_no'] }}</strong>
                                @elseif ($requiresContract)
                                    · <strong style="color:#b91c1c;">RNT missing</strong>
                                @endif
                            </p>
                        @endif
                    </div>

                    @include('filament.resources.violation-resource.informuoti-modal-attachments')
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid #eef2f7;">
                    <div class="flex items-center justify-between gap-3" style="margin: 0 0 10px;">
                        <p class="text-xs font-semibold uppercase text-gray-500" style="margin: 0;">Reply draft</p>
                        @if (! $showTemplateEditor)
                            <x-filament::button size="sm" color="gray" wire:click="openTemplateEditor">Edit text</x-filament::button>
                        @else
                            <x-filament::button size="sm" color="gray" wire:click="closeTemplateEditor">Done editing</x-filament::button>
                        @endif
                    </div>

                    <div class="overflow-auto rounded-lg border border-gray-200 bg-white" style="max-height: 280px; padding: 8px;">
                        {!! $previewDraft['body_html'] ?? '' !!}
                    </div>

                    @if ($showTemplateEditor)
                        <label class="block text-sm" style="margin-top: 12px;">
                            <span class="text-xs font-semibold uppercase text-gray-500">Template text</span>
                            <textarea
                                wire:model.blur="previewDraft.body_text"
                                wire:change="refreshPreviewHtml"
                                rows="6"
                                class="mt-2 w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm"
                                style="padding: 12px 14px;"
                            ></textarea>
                        </label>
                    @endif
                </div>

                <div class="flex flex-wrap justify-end gap-2" style="padding: 16px 24px;">
                    <x-filament::button color="gray" wire:click="closeInformuoti">Close</x-filament::button>
                    @unless (! empty($wheelsAlreadyImported))
                        <x-filament::button color="gray" wire:click="createInWheelsys">Create in Wheels</x-filament::button>
                        <x-filament::button color="gray" wire:click="markWheelsCreated">Mark Wheels done</x-filament::button>
                    @endunless
                    @if ($isEpaslaugos)
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
    </div>
@endif
