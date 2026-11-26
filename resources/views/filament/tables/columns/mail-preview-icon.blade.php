@php
    $info = \App\Support\FineReplyReadiness::forLog($getRecord());
    $ready = ! empty($info['ready']);
    $sent = ! empty($info['sent']);
    if ($sent) {
        $bg = '#e0f2fe';
        $fg = '#0369a1';
        $border = '#7dd3fc';
        $label = 'Sent';
        $title = 'Preview · already sent';
    } elseif ($ready) {
        $bg = '#ecfdf5';
        $fg = '#047857';
        $border = '#86efac';
        $label = 'Preview / Send';
        $title = 'Preview / Send · Ready';
    } else {
        $bg = '#fff7ed';
        $fg = '#c2410c';
        $border = '#fdba74';
        $label = 'Preview / Send';
        $title = 'Preview / Send · Missing fields — edit before send';
    }
@endphp
<button
    type="button"
    wire:click="openInformuoti({{ (int) $getRecord()->id }})"
    title="{{ $title }}"
    aria-label="Preview / Send mail"
    style="
        display:inline-flex;align-items:center;gap:8px;
        min-height:2.35rem;padding:6px 12px 6px 10px;
        border:1px solid {{ $border }};border-radius:10px;
        background:{{ $bg }};color:{{ $fg }};
        cursor:pointer;font-size:12px;font-weight:800;line-height:1.2;
        box-shadow:0 1px 2px rgba(15,23,42,.06);
        white-space:nowrap;
    "
>
    <x-heroicon-o-envelope style="width:1.35rem;height:1.35rem;flex-shrink:0;" />
    <span>{{ $label }}</span>
</button>
