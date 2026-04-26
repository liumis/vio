<?php

namespace App\Providers;

use App\Models\Authority;
use App\Models\Import;
use App\Models\User;
use App\Support\ActivityLogger;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([10, 25, 50, 100, 'all'])
                ->defaultPaginationPageOption(100);
        });

        $this->registerFriendlyCrudActivityLogs();

        Event::listen(Failed::class, function (Failed $event): void {
            ActivityLogger::log('Auth: failed login attempt', $event->user, request()->ip(), [
                'email' => (string) ($event->credentials['email'] ?? ''),
            ]);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            ActivityLogger::log('Auth: password reset completed', $event->user);
        });
    }

    private function registerFriendlyCrudActivityLogs(): void
    {
        Authority::created(fn (Authority $authority) => $this->logModelAction('Inserted authority', $authority));
        Authority::updated(fn (Authority $authority) => $this->logModelAction('Updated authority', $authority));
        Authority::deleted(fn (Authority $authority) => $this->logModelAction('Deleted authority', $authority));

        User::created(fn (User $user) => $this->logModelAction('Inserted user', $user));
        User::updated(fn (User $user) => $this->logModelAction('Updated user', $user));
        User::deleted(fn (User $user) => $this->logModelAction('Deleted user', $user));

        Import::created(function (Import $import): void {
            $this->logModelAction(
                'Imported violations file',
                $import,
                ['rows' => $import->imported_rows, 'file' => $import->file_path]
            );
        });
    }

    private function logModelAction(string $prefix, Model $model, array $meta = []): void
    {
        if (! auth()->check()) {
            return;
        }

        $label = method_exists($model, 'getAttribute')
            ? ($model->getAttribute('name') ?? $model->getKey())
            : $model->getKey();

        ActivityLogger::log("{$prefix}: {$label}", meta: $meta);
    }
}
