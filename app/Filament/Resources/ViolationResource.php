<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ViolationResource\Pages;
use App\Models\Violation;
use App\Support\ActivityLogger;
use App\Support\ViolationEmailSender;
use App\Support\ViolationImportMapping;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Throwable;

class ViolationResource extends Resource
{
    protected static ?string $model = Violation::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Violations';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['import', 'user']);
    }

    public static function table(Table $table): Table
    {
        $labels = ViolationImportMapping::columnLabels();
        $defaultVisible = ViolationImportMapping::DEFAULT_TABLE_VISIBLE_COLUMNS;
        $columnOrder = self::violationTableColumnOrder();

        $excelColumns = [];
        foreach ($columnOrder as $column) {
            $isDefaultVisible = in_array($column, $defaultVisible, true);

            $excelColumns[] = Tables\Columns\TextColumn::make($column)
                ->label($labels[$column] ?? $column)
                ->searchable()
                ->sortable()
                ->toggleable($isDefaultVisible === false, isToggledHiddenByDefault: true)
                ->wrap();
        }

        return $table
            ->recordClasses(function (Violation $record): string {
                $classes = 'text-[11px]';

                if ($record->status === Violation::STATUS_NOT_SENT) {
                    $classes .= ' bg-red-50 hover:bg-red-50/90';
                } elseif ($record->status === Violation::STATUS_FAILED) {
                    $classes .= ' bg-amber-50 hover:bg-amber-50/90';
                }

                return $classes;
            })
            ->columns([
                Tables\Columns\IconColumn::make('status')
                    ->label('')
                    ->alignCenter()
                    ->icons([
                        'heroicon-o-envelope' => Violation::STATUS_NOT_SENT,
                        'heroicon-o-check-circle' => Violation::STATUS_SENT,
                        'heroicon-o-x-circle' => Violation::STATUS_FAILED,
                    ])
                    ->colors([
                        'gray' => Violation::STATUS_NOT_SENT,
                        'success' => Violation::STATUS_SENT,
                        'danger' => Violation::STATUS_FAILED,
                    ])
                    ->disabledClick(fn (Violation $record): bool => $record->status === Violation::STATUS_SENT)
                    ->tooltip(fn (Violation $record): string => match ($record->status) {
                        Violation::STATUS_SENT => __('Sent'),
                        Violation::STATUS_FAILED => __('Failed (click to retry)'),
                        default => __('Send'),
                    })
                    ->action(
                        Action::make('send')
                            ->requiresConfirmation()
                            ->modalHeading(__('Send?'))
                            ->modalDescription(function (Violation $record): HtmlString {
                                $referenceNo = (string) ($record->ticket_number ?? '-');
                                $customerName = (string) ($record->driver ?? '-');
                                $plateNumber = (string) ($record->vehicle ?? $record->driver_license ?? '-');

                                return new HtmlString(
                                    'Reference no: <strong>'.e($referenceNo).'</strong><br>'.
                                    'Customer name: <strong>'.e($customerName).'</strong><br>'.
                                    'Plate number: <strong>'.e($plateNumber).'</strong>'
                                );
                            })
                            ->modalSubmitActionLabel(__('Send'))
                            ->action(function (Violation $record): void {
                                try {
                                    app(ViolationEmailSender::class)->send($record);

                                    $record->update([
                                        'status' => Violation::STATUS_SENT,
                                        'send_error' => null,
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Email sent')
                                        ->body('Notification sent to liumis@gmail.com.')
                                        ->send();
                                } catch (Throwable $e) {
                                    $record->update([
                                        'status' => Violation::STATUS_FAILED,
                                        'send_error' => $e->getMessage(),
                                    ]);

                                    ActivityLogger::log(
                                        sprintf(
                                            'Violation email FAIL: %s | Driver: %s | Email: %s | Car plate: %s | Error: %s (Import #%s, Row %s)',
                                            (string) ($record->ticket_number ?? $record->id),
                                            (string) ($record->driver ?? '-'),
                                            (string) ($record->customer_email ?? '-'),
                                            (string) ($record->vehicle ?? $record->driver_license ?? '-'),
                                            $e->getMessage(),
                                            (string) $record->import_id,
                                            (string) $record->row_number
                                        ),
                                        meta: [
                                            'violation_id' => $record->id,
                                            'send_status' => Violation::STATUS_FAILED,
                                            'send_error' => $e->getMessage(),
                                        ]
                                    );

                                    Notification::make()
                                        ->danger()
                                        ->title('Email not sent')
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            })
                    ),
                Tables\Columns\TextColumn::make('import_id')
                    ->label('Import #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('row_number')
                    ->label('Row')
                    ->sortable(),
                ...$excelColumns,
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Imported By')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        Violation::STATUS_NOT_SENT => __('Not sent'),
                        Violation::STATUS_SENT => __('Sent'),
                        Violation::STATUS_FAILED => __('Failed'),
                    ]),
                Tables\Filters\SelectFilter::make('import_id')
                    ->label('Import')
                    ->relationship('import', 'id')
                    ->default(request()->integer('import_id') ?: null),
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('send_selected')
                        ->label('Send selected')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalHeading('Send selected violations?')
                        ->modalDescription('This will send notification emails to liumis@gmail.com and mark successful ones as sent.')
                        ->modalSubmitActionLabel('Send selected')
                        ->action(function (Collection $records): void {
                            $sentCount = 0;
                            $failedCount = 0;

                            $records
                                ->whereIn('status', [Violation::STATUS_NOT_SENT, Violation::STATUS_FAILED])
                                ->each(function (Violation $record) use (&$sentCount, &$failedCount): void {
                                    try {
                                        app(ViolationEmailSender::class)->send($record);

                                        $record->update([
                                            'status' => Violation::STATUS_SENT,
                                            'send_error' => null,
                                        ]);

                                        $sentCount++;
                                    } catch (Throwable $e) {
                                        $errorMessage = $e->getMessage();

                                        $record->update([
                                            'status' => Violation::STATUS_FAILED,
                                            'send_error' => $errorMessage,
                                        ]);

                                        ActivityLogger::log(
                                            sprintf(
                                                'Violation email FAIL: %s | Driver: %s | Email: %s | Car plate: %s | Error: %s (Import #%s, Row %s)',
                                                (string) ($record->ticket_number ?? $record->id),
                                                (string) ($record->driver ?? '-'),
                                                (string) ($record->customer_email ?? '-'),
                                                (string) ($record->vehicle ?? $record->driver_license ?? '-'),
                                                $errorMessage,
                                                (string) $record->import_id,
                                                (string) $record->row_number
                                            ),
                                            meta: [
                                                'violation_id' => $record->id,
                                                'send_status' => Violation::STATUS_FAILED,
                                                'send_error' => $errorMessage,
                                            ]
                                        );

                                        $failedCount++;
                                    }
                                });

                            Notification::make()
                                ->title('Bulk send finished')
                                ->body("Sent: {$sentCount}, Failed: {$failedCount}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListViolations::route('/'),
        ];
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    private static function violationTableColumnOrder(): array
    {
        $preferred = ViolationImportMapping::DEFAULT_TABLE_VISIBLE_COLUMNS;
        $rest = array_values(array_diff(ViolationImportMapping::COLUMN_NAMES, $preferred));

        return [...$preferred, ...$rest];
    }
}
