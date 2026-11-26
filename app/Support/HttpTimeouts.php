<?php

namespace App\Support;

/**
 * Central outbound HTTP / PHP timeouts (seconds) from config/vio.php.
 */
final class HttpTimeouts
{
    public static function short(): int
    {
        return max(1, (int) config('vio.timeouts.short', 120));
    }

    public static function default(): int
    {
        return max(1, (int) config('vio.timeouts.default', 300));
    }

    public static function long(): int
    {
        return max(1, (int) config('vio.timeouts.long', 600));
    }

    public static function php(): int
    {
        return max(1, (int) config('vio.timeouts.php', 600));
    }

    /**
     * Extend PHP execution for a long Livewire / Artisan action (e.g. Read emails Fetch).
     */
    public static function extendPhpRuntime(): void
    {
        $seconds = self::php();
        @ini_set('max_execution_time', (string) $seconds);
        @ini_set('default_socket_timeout', (string) self::long());
        @set_time_limit($seconds);
    }
}
