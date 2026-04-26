<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(string $action, ?User $user = null, ?string $ip = null, array $meta = []): void
    {
        $resolvedUser = $user ?? Auth::user();

        ActivityLog::create([
            'user_id' => $resolvedUser?->id,
            'user_name' => $resolvedUser?->name ?? ($meta['email'] ?? null),
            'action' => $action,
            'ip_address' => $ip ?? request()->ip(),
            'meta' => empty($meta) ? null : $meta,
        ]);
    }
}
