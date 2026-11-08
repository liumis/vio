<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-slate-200 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm">
        <p style="margin: 0 0 8px;"><strong>Manual procesas</strong> (instrukcija VIII) — be automatinio teismų portalo.</p>
        <ol style="margin: 0; padding-left: 1.2rem; line-height: 1.5;">
            <li>@teismas.lt laišką / dokumentą persiųsti teisininkui ({{ \App\Filament\Pages\CourtDecisions::LAWYER_EMAIL }}).</li>
            <li>Palaukti: apmokėti ar ginčas.</li>
            <li>Suvesti į Wheelsys; nuskaičiuoti baudą + adm. mokestį.</li>
            <li>LV/EE klientas — perduoti padaliniui; pavedimo kopija → vilniaus.apylinkes@teismas.lt.</li>
        </ol>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" color="success" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save court fine</span>
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
