@php
    /** @var array{days_left: int|null, level: string, label: string, due_date: string|null}|null $deadline */
    $deadline = $deadline ?? null;
    $level = is_array($deadline) ? ($deadline['level'] ?? 'none') : 'none';
    $label = is_array($deadline) ? (string) ($deadline['label'] ?? '—') : '—';
    $due = is_array($deadline) ? ($deadline['due_date'] ?? null) : null;
    $chip = match ($level) {
        'warn' => 'vio-chip-warn',
        'critical' => 'vio-chip-critical',
        'done' => 'vio-chip-ok',
        'ok' => 'vio-chip-slate',
        default => 'vio-chip-slate',
    };
@endphp
@if ($level === 'none')
    <span style="color: #cbd5e1;">—</span>
@else
    <span
        class="vio-chip {{ $chip }}{{ $level === 'critical' ? ' vio-chip-pulse' : '' }}"
        @if ($due) title="Terminas: {{ $due }}" @endif
    >{{ $label }}</span>
@endif
