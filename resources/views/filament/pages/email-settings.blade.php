<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Save
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                wire:click="testSharePoint"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="testSharePoint">Test SharePoint upload</span>
                <span wire:loading wire:target="testSharePoint">Testing…</span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="warning"
                wire:click="uploadDebtTrackerCopy"
                wire:loading.attr="disabled"
                wire:target="uploadDebtTrackerCopy"
            >
                <span wire:loading.remove wire:target="uploadDebtTrackerCopy">Upload CHARGEBACKS/DEBTS to SharePoint</span>
                <span wire:loading wire:target="uploadDebtTrackerCopy">Uploading…</span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="info"
                wire:click="renewPowerOfAttorney"
                wire:loading.attr="disabled"
                wire:target="renewPowerOfAttorney"
            >
                <span wire:loading.remove wire:target="renewPowerOfAttorney">Renew Latvia POA → MarkSign</span>
                <span wire:loading wire:target="renewPowerOfAttorney">Creating POA…</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
