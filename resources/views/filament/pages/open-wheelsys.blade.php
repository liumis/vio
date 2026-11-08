<x-filament-panels::page>
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <h2 class="text-lg font-semibold text-gray-950">Wheelsys (GreenMotion)</h2>
        <p class="mt-2 text-sm text-gray-600">
            After you are logged into VIO, use the button below to open Wheelsys already signed in
            as the configured account.
        </p>

        <div class="mt-6">
            <a
                href="{{ $this->getWheelsysSsoUrl() }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-lg bg-[#223a4e] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1a2d3d]"
            >
                <x-heroicon-o-arrow-top-right-on-square class="h-5 w-5" />
                Sign in to Wheelsys
            </a>
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Credentials are in <strong>System settings → Wheelsys</strong>.
            Prefer <strong>Wheelsys import → Start import</strong> for the assisted flow.
            Opens in a new tab.
        </p>
    </div>
</x-filament-panels::page>
