@php
    $attachPreview = is_array($sendAttachmentPreview ?? null) ? $sendAttachmentPreview : null;
    $attachNotice = is_array($attachPreview['notice'] ?? null) ? $attachPreview['notice'] : [];
    $attachContracts = is_array($attachPreview['contracts'] ?? null) ? $attachPreview['contracts'] : [];
    $attachError = trim((string) ($attachPreview['error'] ?? ''));
    $canFetchContract = $hasRentalId || $requiresContract;
    $showAttachPanel = $requiresContract || $canFetchContract || $attachNotice !== [] || $attachContracts !== [] || $attachError !== '';
    $fetchTitle = $requiresContract
        ? 'Required for this authority'
        : 'Optional — available for all authorities when rental is selected';
@endphp
@if ($showAttachPanel)
    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200" style="padding: 14px; margin-top: 12px;">
        <div class="flex flex-wrap items-center justify-between gap-2" style="margin: 0 0 8px;">
            <div>
                <p class="text-xs font-semibold uppercase text-amber-900" style="margin: 0;">Attachments on send</p>
                <p class="text-[11px] text-amber-800" style="margin: 4px 0 0;">Uncheck files you do not want to send (e.g. receipts).</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button size="xs" color="gray" wire:click="refreshSendAttachmentPreview" wire:loading.attr="disabled">
                    Retest attach
                </x-filament::button>
                @if ($canFetchContract)
                    <x-filament::button
                        size="xs"
                        color="warning"
                        wire:click="fetchRntDocsForInformuoti"
                        wire:loading.attr="disabled"
                        :title="$fetchTitle"
                    >
                        Fetch contract → SharePoint
                    </x-filament::button>
                @endif
            </div>
        </div>
        @if ($attachError !== '')
            <p class="text-xs" style="color:#b91c1c;margin:0 0 8px;">{{ $attachError }}</p>
        @endif

        <div class="text-xs text-slate-700" style="line-height:1.45;">
            <div style="margin-bottom:8px;">
                <strong>Notice:</strong>
                @if ($attachNotice === [])
                    <span style="color:#b91c1c;">none found</span>
                @else
                    <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
                        @foreach ($attachNotice as $noticeFile)
                            @php
                                $nKey = (string) ($noticeFile['key'] ?? '');
                                $nName = (string) ($noticeFile['name'] ?? 'notice.pdf');
                                $nUrl = trim((string) ($noticeFile['url'] ?? ''));
                            @endphp
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                                <input
                                    type="checkbox"
                                    value="{{ $nKey }}"
                                    wire:model.live="sendAttachmentSelected"
                                    style="margin-top:2px;"
                                />
                                <span>
                                    @if ($nUrl !== '')
                                        <a href="{{ $nUrl }}" target="_blank" rel="noopener noreferrer" style="color:#0369a1;text-decoration:underline;font-weight:600;" title="Open on SharePoint">{{ $nName }}</a>
                                    @else
                                        <span style="font-weight:600;">{{ $nName }}</span>
                                    @endif
                                    @if (($noticeFile['bytes'] ?? 0) > 0)
                                        <span style="color:#64748b;">· {{ number_format(($noticeFile['bytes'] ?? 0) / 1024, 0) }} KB</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <strong>Contract (RNT){{ $requiresContract ? ' *' : '' }}:</strong>
                @if ($attachContracts !== [])
                    <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">
                        @foreach ($attachContracts as $contractFile)
                            @php
                                $cKey = (string) ($contractFile['key'] ?? '');
                                $cName = (string) ($contractFile['name'] ?? 'contract.pdf');
                                $cUrl = trim((string) ($contractFile['url'] ?? ''));
                            @endphp
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                                <input
                                    type="checkbox"
                                    value="{{ $cKey }}"
                                    wire:model.live="sendAttachmentSelected"
                                    style="margin-top:2px;"
                                />
                                <span>
                                    @if ($cUrl !== '')
                                        <a href="{{ $cUrl }}" target="_blank" rel="noopener noreferrer" style="color:#b45309;text-decoration:underline;font-weight:700;" title="Open on SharePoint">{{ $cName }}</a>
                                    @else
                                        <span style="font-weight:700;color:#b45309;">{{ $cName }}</span>
                                    @endif
                                    @if (($contractFile['bytes'] ?? 0) > 0)
                                        <span style="color:#64748b;">· {{ number_format(($contractFile['bytes'] ?? 0) / 1024, 0) }} KB</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @elseif ($requiresContract)
                    <span style="color:#b91c1c;">must — fetch / check rental attachments</span>
                @elseif ($canFetchContract)
                    <span style="color:#64748b;">optional — fetch if needed</span>
                @else
                    <span style="color:#64748b;">pick rental to enable fetch</span>
                @endif
            </div>
        </div>
    </div>
@endif
