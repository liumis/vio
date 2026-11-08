<?php

namespace App\Filament\Pages;

use App\Models\FineProcessLog;
use App\Support\EpaslaugosAssist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Periodinis IRD ePaslaugos „Atmesti“ tikrinimas (instrukcija II.6).
 */
class EpaslaugosFollowUp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'ePaslaugos Atmesti';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'ePaslaugos · Atmesti follow-up';

    protected static ?string $slug = 'epaslaugos-atmesti';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.epaslaugos-follow-up';

    /**
     * @return list<array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        return FineProcessLog::query()
            ->whereNotNull('workflow')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (FineProcessLog $log): ?array {
                $workflow = is_array($log->workflow) ? $log->workflow : [];
                $epa = is_array($workflow['epaslaugos'] ?? null) ? $workflow['epaslaugos'] : null;
                if ($epa === null) {
                    return null;
                }

                $status = (string) ($epa['atmesti_status'] ?? 'pending');
                $submittedAt = isset($epa['submitted_at']) ? Carbon::parse($epa['submitted_at']) : null;
                $checkedAt = isset($epa['atmesti_checked_at']) ? Carbon::parse($epa['atmesti_checked_at']) : null;
                $due = $status === 'pending' && (
                    $checkedAt === null
                    || $checkedAt->lt(now()->subDays(3))
                );

                return [
                    'id' => $log->id,
                    'reference' => $log->reference,
                    'vehicle' => $log->vehicle,
                    'driver' => $log->driver_name,
                    'authority' => $log->authority_name,
                    'person_type' => $epa['person_type'] ?? EpaslaugosAssist::PERSON_LT_NATURAL,
                    'atmesti_status' => $status,
                    'submitted_at' => $submittedAt?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'checked_at' => $checkedAt?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'note' => $epa['atmesti_note'] ?? '',
                    'due' => $due,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = FineProcessLog::query()
                ->whereNotNull('workflow')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->filter(function (FineProcessLog $log): bool {
                    $epa = is_array($log->workflow['epaslaugos'] ?? null) ? $log->workflow['epaslaugos'] : null;
                    if ($epa === null) {
                        return false;
                    }
                    if (($epa['atmesti_status'] ?? 'pending') !== 'pending') {
                        return false;
                    }
                    $checked = isset($epa['atmesti_checked_at'])
                        ? Carbon::parse($epa['atmesti_checked_at'])
                        : null;

                    return $checked === null || $checked->lt(now()->subDays(3));
                })
                ->count();
        } catch (\Throwable) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public function markClear(int $id): void
    {
        $this->updateAtmesti($id, 'clear', 'No rejection found');
    }

    public function markRejected(int $id): void
    {
        $this->updateAtmesti($id, 'rejected', 'Found in Atmesti — re-submit required');
    }

    public function markRecheckLater(int $id): void
    {
        $this->updateAtmesti($id, 'pending', 'Checked — still monitoring');
    }

    private function updateAtmesti(int $id, string $status, string $note): void
    {
        $log = FineProcessLog::query()->find($id);
        if (! $log) {
            Notification::make()->danger()->title('Log not found')->send();

            return;
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $epa = is_array($workflow['epaslaugos'] ?? null) ? $workflow['epaslaugos'] : [];
        $epa['atmesti_status'] = $status;
        $epa['atmesti_checked_at'] = now()->toIso8601String();
        $epa['atmesti_note'] = $note;
        $workflow['epaslaugos'] = $epa;

        if (is_array($workflow['steps'] ?? null)) {
            $workflow['steps'] = array_map(function (array $step) use ($status, $note): array {
                if (($step['key'] ?? '') === 'epaslaugos_atmesti') {
                    $step['ok'] = in_array($status, ['clear', 'rejected'], true);
                    $step['detail'] = $note.($status === 'rejected' ? ' · needs re-submit' : '');
                }

                return $step;
            }, $workflow['steps']);
        }

        $log->fill(['workflow' => $workflow])->save();

        Notification::make()
            ->success()
            ->title('Atmesti updated')
            ->body('#'.$id.' → '.$status)
            ->send();
    }
}
