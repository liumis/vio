<?php

namespace App\Providers\Filament;

use App\Filament\Pages\IncomingFines;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
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

    /* Full-bleed content: sidebar fixed left, main uses remaining width (no fat gutters). */
    .fi-main-ctn,
    .fi-page-content,
    .fi-header,
    .fi-main > section {
        max-width: none !important;
    }
    .fi-main {
        padding-inline: 0.75rem !important;
    }
    @media (min-width: 1024px) {
        .fi-main {
            padding-inline: 1rem !important;
        }
    }
    .fi-page > .fi-header {
        padding-inline: 0 !important;
    }
    .fi-page-header-main-ctn,
    .fi-header-heading {
        padding-inline: 0 !important;
    }

    /* Fines = core 3-step workflow — highlight the whole group. */
    .fi-sidebar-nav .fi-sidebar-group:has(a.fi-sidebar-item-button[href*="incoming-fines"]) {
        margin: 0.35rem 0.45rem 0.85rem !important;
        padding: 0.55rem 0.4rem 0.65rem !important;
        border-radius: 0.85rem !important;
        background:
            linear-gradient(180deg, rgba(46, 204, 113, 0.12) 0%, rgba(34, 58, 78, 0.06) 100%),
            #f4faf7 !important;
        border: 1px solid #86efac !important;
        box-shadow: 0 8px 22px rgba(34, 58, 78, 0.08) !important;
    }
    .fi-sidebar-nav .fi-sidebar-group:has(a.fi-sidebar-item-button[href*="incoming-fines"]) > .fi-sidebar-group-button,
    .fi-sidebar-nav .fi-sidebar-group:has(a.fi-sidebar-item-button[href*="incoming-fines"]) .fi-sidebar-group-label {
        color: #166534 !important;
        font-weight: 800 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
        font-size: 0.7rem !important;
    }
    .fi-sidebar-nav .fi-sidebar-group:has(a.fi-sidebar-item-button[href*="incoming-fines"]) .fi-sidebar-group-label::after {
        content: " · 3 steps";
        font-weight: 650;
        opacity: 0.75;
        text-transform: none;
        letter-spacing: 0;
    }
    .fi-sidebar-nav .fi-sidebar-group:has(a.fi-sidebar-item-button[href*="incoming-fines"]) a.fi-sidebar-item-button {
        border-radius: 0.6rem !important;
        font-weight: 650 !important;
        margin-top: 0.2rem !important;
    }
    .fi-sidebar-nav .fi-sidebar-group:has(a.fi-sidebar-item-button[href*="incoming-fines"]) a.fi-sidebar-item-button:hover {
        background: rgba(255, 255, 255, 0.72) !important;
    }

    /* Tools CTAs — Manual search / New manual fine */
    a.fi-sidebar-item-button[href*="wheelsys-manual-search"],
    a.fi-sidebar-item-button[href*="/new-fine"] {
        border-radius: 0.65rem !important;
        background: #e8eef3 !important;
        border: 1px solid #cbd5e1 !important;
        color: #223a4e !important;
        font-weight: 650 !important;
        box-shadow: none !important;
        padding-inline: 0.7rem !important;
        margin-top: 0.2rem !important;
    }
    a.fi-sidebar-item-button[href*="wheelsys-manual-search"]:hover,
    a.fi-sidebar-item-button[href*="/new-fine"]:hover {
        background: #dbe4ec !important;
        border-color: #94a3b8 !important;
        color: #1a2d3d !important;
    }
    a.fi-sidebar-item-button[href*="wheelsys-manual-search"] .fi-sidebar-item-label,
    a.fi-sidebar-item-button[href*="/new-fine"] .fi-sidebar-item-label,
    a.fi-sidebar-item-button[href*="wheelsys-manual-search"] .fi-sidebar-item-icon,
    a.fi-sidebar-item-button[href*="/new-fine"] .fi-sidebar-item-icon {
        color: #223a4e !important;
    }

    /* Incoming footer sits in the main column only (not under sidebar). */
    .vio-footer-bar {
        left: 0 !important;
        right: 0 !important;
    }
    @media (min-width: 1024px) {
        .vio-footer-bar {
            left: var(--sidebar-width, 15rem) !important;
        }
        /* Desktop collapsed icon rail */
        .fi-sidebar:not(.fi-sidebar-open) ~ * .vio-footer-bar,
        body:has(.fi-sidebar:not(.fi-sidebar-open)) .vio-footer-bar {
            left: var(--collapsed-sidebar-width, 4.5rem) !important;
        }
        body:has(.fi-sidebar.fi-sidebar-open) .vio-footer-bar {
            left: var(--sidebar-width, 15rem) !important;
        }
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
            ->homeUrl(fn (): string => IncomingFines::getUrl())
            ->colors([
                'primary' => Color::hex('#223a4e'),
                'success' => Color::hex('#2ecc71'),
                'danger' => Color::hex('#ff5e8e'),
            ])
            ->darkMode(false)
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarWidth('15rem')
            ->collapsedSidebarWidth('4.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Fines')->collapsed(false),
                NavigationGroup::make('Tools')->collapsed(false),
                NavigationGroup::make('Settings')->collapsed(false),
            ])
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
