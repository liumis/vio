<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OpenWheelsys extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-top-right-on-square';

    protected static ?string $navigationLabel = 'Open Wheelsys';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'Open Wheelsys';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.open-wheelsys';

    /**
     * Hidden from nav — SSO is opened from Wheelsys import (Start import modal).
     * Credentials live in System settings.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getWheelsysSsoUrl(): string
    {
        return route('wheelsys.sso');
    }
}
