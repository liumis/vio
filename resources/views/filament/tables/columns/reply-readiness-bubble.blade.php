@php
    /** @var \App\Models\FineProcessLog $record */
    $record = $getRecord();
    $info = \App\Support\FineReplyReadiness::forLog($record);
    $bg = $info['sent'] ? '#e0f2fe' : ($info['ready'] ? '#dcfce7' : '#fee2e2');
    $fg = $info['sent'] ? '#0369a1' : ($info['ready'] ? '#166534' : '#b91c1c');
    $dot = $info['sent'] ? '#0ea5e9' : ($info['ready'] ? '#22c55e' : '#ef4444');
@endphp
<span
    title="{{ $info['detail'] }}"
    style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;line-height:1.35;background:{{ $bg }};color:{{ $fg }};white-space:nowrap;"
>
    <span
        aria-hidden="true"
        style="width:8px;height:8px;border-radius:999px;background:{{ $dot }};box-shadow:0 0 0 3px {{ $dot }}33;flex-shrink:0;"
    ></span>
    {{ $info['label'] }}
</span>
