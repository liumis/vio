<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Import $import): void {
            $import->violations()->delete();
        });
    }

    protected $fillable = [
        'file_path',
        'imported_rows',
        'imported_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
