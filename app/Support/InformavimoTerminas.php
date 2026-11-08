<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\FineProcessLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Days left to inform an authority about the driver, from letter received date.
 */
final class InformavimoTerminas
{
    public const LEVEL_NONE = 'none';

    public const LEVEL_OK = 'ok';

    public const LEVEL_WARN = 'warn';

    public const LEVEL_CRITICAL = 'critical';

    public const LEVEL_DONE = 'done';

    /**
     * @return array{
     *     days_left: int|null,
     *     level: string,
     *     label: string,
     *     due_date: string|null,
     *     tipas: string|null,
     *     terminas: int|null
     * }
     */
    public static function evaluate(
        ?Authority $authority,
        CarbonInterface|string|null $receivedAt,
        bool $informed = false,
        CarbonInterface|string|null $asOf = null,
    ): array {
        $empty = [
            'days_left' => null,
            'level' => self::LEVEL_NONE,
            'label' => '—',
            'due_date' => null,
            'tipas' => null,
            'terminas' => null,
        ];

        if ($informed) {
            return [
                'days_left' => null,
                'level' => self::LEVEL_DONE,
                'label' => 'Informuota',
                'due_date' => null,
                'tipas' => $authority?->informavimo_terminas_tipas,
                'terminas' => $authority?->informavimo_terminas !== null
                    ? (int) $authority->informavimo_terminas
                    : null,
            ];
        }

        if ($authority === null || $authority->informavimo_terminas === null) {
            return $empty;
        }

        $terminas = (int) $authority->informavimo_terminas;
        if ($terminas < 1) {
            return $empty;
        }

        $tipas = ($authority->informavimo_terminas_tipas ?? 'working') === 'calendar'
            ? 'calendar'
            : 'working';

        $received = self::toLocalDay($receivedAt);
        if ($received === null) {
            return $empty;
        }

        $today = self::toLocalDay($asOf ?? now()) ?? now()->timezone(config('app.timezone'))->startOfDay();
        $due = $tipas === 'calendar'
            ? $received->copy()->addDays($terminas)
            : self::addWorkingDays($received, $terminas);

        $daysLeft = $tipas === 'calendar'
            ? self::signedCalendarDaysBetween($today, $due)
            : self::signedWorkingDaysBetween($today, $due);

        $level = self::LEVEL_OK;
        if ($daysLeft <= 0) {
            $level = self::LEVEL_CRITICAL;
        } elseif ($daysLeft <= 2) {
            $level = self::LEVEL_WARN;
        }

        return [
            'days_left' => $daysLeft,
            'level' => $level,
            'label' => self::label($daysLeft, $tipas),
            'due_date' => $due->format('Y-m-d'),
            'tipas' => $tipas,
            'terminas' => $terminas,
        ];
    }

    /**
     * @return array{days_left: int|null, level: string, label: string, due_date: string|null, tipas: string|null, terminas: int|null}
     */
    public static function forLog(FineProcessLog $log, ?Authority $authority = null): array
    {
        $auth = $authority ?? $log->authority;

        if ($auth === null && filled($log->authority_name)) {
            static $byName = [];
            $name = trim((string) $log->authority_name);
            if (! array_key_exists($name, $byName)) {
                $byName[$name] = Authority::query()->where('name', $name)->first();
            }
            $auth = $byName[$name];
        }

        $informed = filled($log->email_sent_at);

        return self::evaluate($auth, $log->received_at, $informed);
    }

    /**
     * @return array{days_left: int|null, level: string, label: string, due_date: string|null, tipas: string|null, terminas: int|null}
     */
    public static function forAuthorityId(
        ?int $authorityId,
        CarbonInterface|string|null $receivedAt,
        bool $informed = false,
    ): array {
        if (! $authorityId) {
            return self::evaluate(null, $receivedAt, $informed);
        }

        static $cache = [];
        if (! array_key_exists($authorityId, $cache)) {
            $cache[$authorityId] = Authority::query()->find($authorityId);
        }

        return self::evaluate($cache[$authorityId], $receivedAt, $informed);
    }

    public static function label(int $daysLeft, string $tipas = 'working'): string
    {
        $unit = $tipas === 'calendar' ? 'd.' : 'd.d.';

        if ($daysLeft > 0) {
            return 'Liko '.$daysLeft.' '.$unit;
        }
        if ($daysLeft === 0) {
            return 'Liko 0 '.$unit;
        }

        return 'Vėluoja '.abs($daysLeft).' '.$unit;
    }

    public static function addWorkingDays(CarbonInterface $start, int $days): Carbon
    {
        $date = $start->copy()->startOfDay();
        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $added++;
            }
        }

        return $date;
    }

    public static function signedCalendarDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $a = $from->copy()->startOfDay();
        $b = $to->copy()->startOfDay();
        if ($a->equalTo($b)) {
            return 0;
        }

        $abs = (int) $a->diffInDays($b);

        return $a->lessThan($b) ? $abs : -$abs;
    }

    /**
     * Working days from $from to $to. Same day → 0. $to before $from → negative.
     */
    public static function signedWorkingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $a = $from->copy()->startOfDay();
        $b = $to->copy()->startOfDay();
        if ($a->equalTo($b)) {
            return 0;
        }

        $sign = $a->lessThan($b) ? 1 : -1;
        $cursor = $a->copy();
        $end = $b->copy();
        if ($sign < 0) {
            [$cursor, $end] = [$end, $cursor];
        }

        $count = 0;
        while ($cursor->lessThan($end)) {
            $cursor->addDay();
            if (! $cursor->isWeekend()) {
                $count++;
            }
        }

        return $sign * $count;
    }

    private static function toLocalDay(CarbonInterface|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dt = $value instanceof CarbonInterface
                ? Carbon::instance($value)->timezone(config('app.timezone'))
                : Carbon::parse((string) $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $dt->startOfDay();
    }
}
