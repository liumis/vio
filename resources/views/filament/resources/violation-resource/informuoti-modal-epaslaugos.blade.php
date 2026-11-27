@if (! empty($epaslaugosAssist) && is_array($epaslaugosAssist))
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
        style="padding: 14px 24px; border-bottom: 1px solid #fde68a; background: #fffbeb;"
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
                x-on:click="copyText(@js($epaslaugosAssist['roik']), 'ROIK copied')"
                :disabled="($epaslaugosAssist['roik'] ?? '') === ''"
            >
                Copy ROIK{{ ($epaslaugosAssist['roik'] ?? '') !== '' ? ' ('.$epaslaugosAssist['roik'].')' : '' }}
            </x-filament::button>
            <x-filament::button
                size="sm"
                color="gray"
                type="button"
                x-on:click="copyText(@js($epaslaugosAssist['vehicle']), 'Vehicle copied')"
                :disabled="($epaslaugosAssist['vehicle'] ?? '') === ''"
            >
                Copy plate{{ ($epaslaugosAssist['vehicle'] ?? '') !== '' ? ' ('.$epaslaugosAssist['vehicle'].')' : '' }}
            </x-filament::button>
            <x-filament::button
                size="sm"
                color="gray"
                type="button"
                x-on:click="copyText(@js($epaslaugosAssist['driver_block']), 'Driver block copied')"
                :disabled="($epaslaugosAssist['driver_block'] ?? '') === ''"
            >
                Copy all fields
            </x-filament::button>
        </div>
        @if (! empty($epaslaugosAssist['fields']))
            <div class="grid gap-2 sm:grid-cols-2" style="margin-bottom: 8px;">
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
                            x-on:click="copyText(@js($field['value']), @js(($field['label'] ?? 'Field').' copied'))"
                        >
                            Copy
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
        @if (! empty($epaslaugosAssist['checklist']))
            <ol class="text-xs text-amber-900" style="margin: 0; padding-left: 1.1rem; line-height: 1.45;">
                @foreach ($epaslaugosAssist['checklist'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        @endif
    </div>
@endif
