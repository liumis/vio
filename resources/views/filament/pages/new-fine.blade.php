<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
        Use this for <strong>physically received</strong> letters (post / scans), e.g. Latvian CPP.
        After save, the fine appears in <strong>Wheelsys import</strong> as Ready.
        Files are stored on SharePoint as <code>{Driver} {Reference}.pdf</code>.
    </div>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" color="success" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save fine</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                tag="a"
                :href="\App\Filament\Pages\WheelsExcelExport::getUrl()"
            >
                Open Wheelsys import
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
