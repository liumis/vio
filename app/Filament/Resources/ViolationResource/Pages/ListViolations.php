<?php

namespace App\Filament\Resources\ViolationResource\Pages;

use App\Filament\Concerns\InteractsWithFineInformavimas;
use App\Filament\Pages\WheelsExcelExport;
use App\Filament\Resources\ViolationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListViolations extends ListRecords
{
    use InteractsWithFineInformavimas;

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

    public function getFooter(): ?View
    {
        // Footer views are not auto-injected with Livewire public props — pass explicitly.
        return view('filament.resources.violation-resource.informuoti-modal', [
            'showInformuoti' => $this->showInformuoti,
            'previewDraft' => $this->previewDraft,
            'wheelsysResults' => $this->wheelsysResults,
            'wheelsysSelected' => $this->wheelsysSelected,
            'wheelsysSelectedIndex' => $this->wheelsysSelectedIndex,
            'searchVehicle' => $this->searchVehicle,
            'searchTicketDate' => $this->searchTicketDate,
            'showTemplateEditor' => $this->showTemplateEditor,
            'sourceMailHtml' => $this->sourceMailHtml,
            'sourceExtractText' => $this->sourceExtractText,
            'sourceFiles' => $this->sourceFiles,
            'informuotiReadiness' => $this->informuotiReadiness,
        ]);
    }
}
