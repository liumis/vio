@php
    $attachPreview = is_array($sendAttachmentPreview ?? null) ? $sendAttachmentPreview : null;
    $attachNotice = is_array($attachPreview['notice'] ?? null) ? $attachPreview['notice'] : [];
    $attachContracts = is_array($attachPreview['contracts'] ?? null) ? $attachPreview['contracts'] : [];
    $attachError = trim((string) ($attachPreview['error'] ?? ''));
    $canFetchContract = $hasRentalId || $requiresContract;

    $renderAttachLinks = static function (array $files, string $fallbackName, string $linkStyle): string {
        $parts = [];
        foreach ($files as $file) {
            $name = e($file['name'] ?? $fallbackName);
            $url = trim((string) ($file['url'] ?? ''));
            if ($url !== '') {
                $parts[] = '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" style="'.$linkStyle.'" title="Open on SharePoint">'.$name.'</a>';
            } else {
                $parts[] = '<span>'.$name.'</span>';
            }
        }

        return implode('', $parts);
    };

    $noticeHtml = $attachNotice === []
        ? '<span style="color:#b91c1c;">none found</span>'
        : '<span style="display:inline-flex;flex-wrap:wrap;gap:6px;margin-top:4px;">'
            .$renderAttachLinks($attachNotice, 'notice.pdf', 'color:#0369a1;text-decoration:underline;font-weight:600;')
            .'</span>';

    if ($attachContracts !== []) {
        $contractHtml = '<span style="display:inline-flex;flex-wrap:wrap;gap:6px;margin-top:4px;">'
            .$renderAttachLinks($attachContracts, 'contract.pdf', 'color:#b45309;text-decoration:underline;font-weight:700;')
            .'</span>';
    } elseif ($requiresContract) {
        $contractHtml = '<span style="color:#b91c1c;">must — fetch / check rental attachments</span>';
    } elseif ($canFetchContract) {
        $contractHtml = '<span style="color:#64748b;">optional — fetch if needed</span>';
    } else {
        $contractHtml = '<span style="color:#64748b;">pick rental to enable fetch</span>';
    }

    $showAttachPanel = $requiresContract || $canFetchContract || $attachNotice !== [] || $attachContracts !== [] || $attachError !== '';
    $fetchTitle = $requiresContract
        ? 'Required for this authority'
        : 'Optional — available for all authorities when rental is selected';
@endphp
@if ($showAttachPanel)
    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200" style="padding: 14px; margin-top: 12px;">
        <div class="flex flex-wrap items-center justify-between gap-2" style="margin: 0 0 8px;">
            <p class="text-xs font-semibold uppercase text-amber-900" style="margin: 0;">Attachments on send</p>
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
            <div><strong>Notice:</strong> {!! $noticeHtml !!}</div>
            <div style="margin-top:6px;">
                <strong>Contract (RNT){{ $requiresContract ? ' *' : '' }}:</strong>
                {!! $contractHtml !!}
            </div>
        </div>
    </div>
@endif
