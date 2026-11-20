@php
    $ready = \App\Support\FineReplyReadiness::forLog($getRecord())['ready'];
    $color = $ready ? '#047857' : '#c2410c';
    $title = $ready
        ? 'Preview / Send · Ready'
        : 'Preview / Send · Missing fields — edit before send';
@endphp
<button
    type="button"
    wire:click="openInformuoti({{ (int) $getRecord()->id }})"
    title="{{ $title }}"
    aria-label="Preview / Send mail"
    style="display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border:0;border-radius:9999px;background:transparent;cursor:pointer;color:{{ $color }};"
>
    <x-heroicon-o-envelope style="width:1.25rem;height:1.25rem;" />
</button>
