<?php

namespace App\Providers\Filament;

use App\Filament\Resources\ImportResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): HtmlString => new HtmlString(<<<'HTML'
<style>
    .fi-ta-row.violation-missing-birth-date > td {
        background-color: #fecaca !important;
    }

    .fi-ta-row.violation-missing-birth-date:hover > td {
        background-color: #fca5a5 !important;
    }

    .fi-ta-row.violation-email-failed > td {
        background-color: #fef3c7 !important;
    }

    .fi-ta-row.violation-email-failed:hover > td {
        background-color: #fde68a !important;
    }
</style>
HTML),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->passwordReset()
            ->brandName('Sit&Go')
            ->brandLogo(asset('images/brand/sitandgo-logo.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/brand/favicon.png'))
            ->homeUrl(fn (): string => ImportResource::getUrl('index'))
            ->colors([
                'primary' => Color::hex('#223a4e'),
                'success' => Color::hex('#2ecc71'),
                'danger' => Color::hex('#ff5e8e'),
            ])
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
