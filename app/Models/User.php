<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Support\ActivityLogger;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function sendPasswordResetNotification($token): void
    {
        ActivityLogger::log('Auth: password reset link sent', $this);
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
