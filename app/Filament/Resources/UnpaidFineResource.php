<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnpaidFineResource\Pages;
use App\Models\FineProcessLog;
use App\Support\WheelsysImportVerifier;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Unpaid fines — manual debtors-list selection only (no auto LT debt write).
 */
class UnpaidFineResource extends Resource
{
    protected static ?string $model = FineProcessLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Unpaid';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $slug = 'unpaid';

    protected static ?string $modelLabel = 'unpaid fine';

    protected static ?string $pluralModelLabel = 'unpaid fines';

    protected static ?int $navigationSort = 70;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('paid_at')
            ->where(function (Builder $query): void {
                // Same operational set as Imported fines (+ chargeback cases).
                $query->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
                    ->orWhereNotNull('chargeback_status');
            })
            ->with(['authority']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ticket_date', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('debt_list_marked_at')
                    ->label('Debt list')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->getStateUsing(fn (FineProcessLog $record): bool => $record->isMarkedForDebtList()),
                Tables\Columns\TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ticket_date')
                    ->label('Ticket date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('authority_name')
                    ->label('Authority')
                    ->wrap()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('agr_no')
                    ->label('RNT')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('wheels_tv_state')
                    ->label('Wheels state')
                    ->badge()
                    ->placeholder('—')
                    ->getStateUsing(fn (FineProcessLog $record): string => WheelsysImportVerifier::stateLabel(
                        $record->wheels_tv_state,
                        $record->wheels_paid_date,
                    ))
                    ->color(fn (FineProcessLog $record): string => filled($record->wheels_paid_date)
                        ? 'success'
                        : ((string) $record->wheels_tv_state === '1' ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('chargeback_status')
                    ->label('Chargeback')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        FineProcessLog::CHARGEBACK_AWAITING_SIGNATURE => 'Awaiting MarkSign',
                        FineProcessLog::CHARGEBACK_SIGNED => 'Signed',
                        FineProcessLog::CHARGEBACK_NOTICE_SENT => 'Notice sent',
                        default => $state ? ucfirst($state) : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        FineProcessLog::CHARGEBACK_AWAITING_SIGNATURE => 'warning',
                        FineProcessLog::CHARGEBACK_SIGNED => 'success',
                        FineProcessLog::CHARGEBACK_NOTICE_SENT => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('debt_list_marked_at')
                    ->label('Marked for debt')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('debt_list')
                    ->label('Debtors list')
                    ->placeholder('All unpaid')
                    ->trueLabel('Marked for debtors')
                    ->falseLabel('Not marked')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('debt_list_marked_at'),
                        false: fn (Builder $query) => $query->whereNull('debt_list_marked_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('markDebtList')
                    ->label('Į skolininkus')
                    ->icon('heroicon-o-queue-list')
                    ->color('warning')
                    ->visible(fn (FineProcessLog $record): bool => ! $record->isMarkedForDebtList())
                    ->requiresConfirmation()
                    ->modalHeading('Pažymėti skolininkų sąrašui')
                    ->modalDescription('Tik rankinė žyma VIO. LT debt / SharePoint automatiškai nerašoma.')
                    ->action(function (FineProcessLog $record): void {
                        $record->fill([
                            'debt_list_marked_at' => now(),
                            'debt_list_marked_by' => Auth::id(),
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('Pažymėta skolininkų sąrašui')
                            ->send();
                    }),
                Tables\Actions\Action::make('unmarkDebtList')
                    ->label('Nuimti žymą')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (FineProcessLog $record): bool => $record->isMarkedForDebtList())
                    ->action(function (FineProcessLog $record): void {
                        $record->fill([
                            'debt_list_marked_at' => null,
                            'debt_list_marked_by' => null,
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('Žyma nuimta')
                            ->send();
                    }),
                Tables\Actions\Action::make('markPaid')
                    ->label('Apmokėta')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pažymėti kaip apmokėtą')
                    ->modalDescription('Dings iš Unpaid sąrašo.')
                    ->action(function (FineProcessLog $record): void {
                        $record->fill([
                            'paid_at' => now(),
                            'paid_marked_by' => Auth::id(),
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('Pažymėta kaip apmokėta')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markDebtListBulk')
                        ->label('Į skolininkus')
                        ->icon('heroicon-o-queue-list')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $userId = Auth::id();
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record instanceof FineProcessLog || $record->isMarkedForDebtList()) {
                                    continue;
                                }
                                $record->fill([
                                    'debt_list_marked_at' => now(),
                                    'debt_list_marked_by' => $userId,
                                ])->save();
                                $count++;
                            }

                            Notification::make()
                                ->success()
                                ->title("Pažymėta skolininkams: {$count}")
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('unmarkDebtListBulk')
                        ->label('Nuimti skolininkų žymą')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record instanceof FineProcessLog || ! $record->isMarkedForDebtList()) {
                                    continue;
                                }
                                $record->fill([
                                    'debt_list_marked_at' => null,
                                    'debt_list_marked_by' => null,
                                ])->save();
                                $count++;
                            }

                            Notification::make()
                                ->success()
                                ->title("Nuimta žymų: {$count}")
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('markPaidBulk')
                        ->label('Apmokėta')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $userId = Auth::id();
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record instanceof FineProcessLog || $record->isPaid()) {
                                    continue;
                                }
                                $record->fill([
                                    'paid_at' => now(),
                                    'paid_marked_by' => $userId,
                                ])->save();
                                $count++;
                            }

                            Notification::make()
                                ->success()
                                ->title("Apmokėta: {$count}")
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Nėra neapmokėtų baudų')
            ->emptyStateDescription('Pažymėk eilutes checkbox’ais → Bulk actions → Į skolininkus / Apmokėta. Arba Sync from Wheelsys pagal PAIDDATE.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnpaidFines::route('/'),
        ];
    }
}
