<?php

namespace App\Filament\Resources\ViolationResource\Pages;

use App\Filament\Pages\WheelsExcelExport;
use App\Filament\Resources\ViolationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListViolations extends ListRecords
{
    protected static string $resource = ViolationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('wheelsysImport')
                ->label('Wheelsys import')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(WheelsExcelExport::getUrl()),
        ];
    }
}
