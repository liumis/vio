<?php

namespace App\Models;

use App\Support\ActivityLogger;
use App\Support\ViolationImportMapping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Violation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (Violation $violation): void {
            if (! $violation->wasChanged('status')) {
                return;
            }

            if ($violation->status !== self::STATUS_SENT) {
                return;
            }

            $driverName = (string) ($violation->driver ?? '-');
            $driverEmail = (string) ($violation->customer_email ?? '-');
            $carPlateNumber = (string) ($violation->vehicle ?? $violation->driver_license ?? '-');
            $sendError = trim((string) ($violation->send_error ?? ''));

            ActivityLogger::log(
                sprintf(
                    'Violation email SUCCESS: %s | Driver: %s | Email: %s | Car plate: %s (Import #%s, Row %s)',
                    (string) ($violation->ticket_number ?? $violation->id),
                    $driverName,
                    $driverEmail,
                    $carPlateNumber,
                    (string) $violation->import_id,
                    (string) $violation->row_number
                ),
                meta: [
                    'violation_id' => $violation->id,
                    'import_id' => $violation->import_id,
                    'row_number' => $violation->row_number,
                    'ticket_number' => $violation->ticket_number,
                    'driver_name' => $driverName,
                    'driver_email' => $driverEmail,
                    'car_plate_number' => $carPlateNumber,
                    'send_status' => self::STATUS_SENT,
                ]
            );
        });
    }

    public const STATUS_NOT_SENT = 'not_sent';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'import_id',
        'user_id',
        'row_number',
        'row_data',
        'status',
        'send_error',
        ...ViolationImportMapping::COLUMN_NAMES,
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
