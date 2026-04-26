<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! auth()->check()) {
            return $response;
        }

        if ($request->is('livewire/*') || $request->is('filament/*')) {
            return $response;
        }

        $path = '/'.ltrim($request->path(), '/');
        $action = sprintf('Viewed page: %s', $path === '//' ? '/' : $path);

        ActivityLogger::log($action, meta: [
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
