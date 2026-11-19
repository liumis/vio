<?php

namespace App\Filament\Resources\UnpaidFineResource\Pages;

use App\Filament\Resources\UnpaidFineResource;
use App\Models\FineProcessLog;
use App\Support\WheelsysImportVerifier;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListUnpaidFines extends ListRecords
{
    protected static string $resource = UnpaidFineResource::class;

    protected static ?string $title = 'Unpaid';

    /**
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncWheelsState')
                ->label('Sync from Wheelsys')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync Wheelsys State / PAIDDATE')
                ->modalDescription('Nuskaito Traffic Violation State ir PAIDDATE. Jei Wheels turi paid date — VIO pažymi Apmokėta.')
                ->action(function (): void {
                    try {
                        $logs = FineProcessLog::query()
                            ->whereNull('paid_at')
                            ->where(function ($q): void {
                                $q->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
                                    ->orWhereNotNull('chargeback_status');
                            })
                            ->whereNotNull('vehicle')
                            ->where('vehicle', '!=', '')
                            ->orderByDesc('id')
                            ->limit(200)
                            ->get();

                        $result = app(WheelsysImportVerifier::class)->syncPaymentStatus($logs, Auth::id());
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Wheelsys sync failed')
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }

                    $body = $result['checked'].' checked · '.$result['paid'].' marked paid · '
                        .$result['updated'].' updated · '.$result['missing'].' not found in Wheels';
                    if ($result['errors'] !== []) {
                        $body .= ' · '.count($result['errors']).' error(s)';
                    }

                    $notification = Notification::make()
                        ->title('Wheelsys sync done')
                        ->body($body);
                    if ($result['paid'] > 0) {
                        $notification->success();
                    } elseif ($result['errors'] !== []) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }
                    $notification->send();
                }),
        ];
    }
}
