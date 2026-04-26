<?php

namespace App\Filament\Resources\ViolationResource\Pages;

use App\Filament\Resources\ImportResource;
use App\Filament\Resources\ViolationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListViolations extends ListRecords
{
    protected static string $resource = ViolationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(ImportResource::getUrl('create')),
        ];
    }
}
