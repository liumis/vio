<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ViolationResource\Pages;
use App\Models\Violation;
use App\Support\ActivityLogger;
use App\Support\ViolationEmailSender;
use App\Support\ViolationImportMapping;
use Filament\Forms;
use Filament\Forms\Get;
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
        $afterSentAtColumns = ['driver', 'birth_date', 'customer_email'];
        $remainingColumnOrder = array_values(array_filter(
            $columnOrder,
            fn (string $column): bool => ! in_array($column, $afterSentAtColumns, true)
        ));

        $makeExcelColumn = static function (string $column) use ($labels, $defaultVisible): Tables\Columns\TextColumn {
            $isDefaultVisible = in_array($column, $defaultVisible, true);

            return Tables\Columns\TextColumn::make($column)
                ->label($labels[$column] ?? $column)
                ->searchable()
                ->sortable()
                ->toggleable($isDefaultVisible === false, isToggledHiddenByDefault: true)
                ->wrap();
        };

        $afterSentAtExcelColumns = array_map(
            static function (string $column) use ($labels, $defaultVisible, $makeExcelColumn): Tables\Columns\TextColumn {
                if ($column === 'birth_date') {
                    return self::makeBirthDateColumn($labels, $defaultVisible);
                }

                return $makeExcelColumn($column);
            },
            array_values(array_filter(
                $afterSentAtColumns,
                fn (string $column): bool => in_array($column, $columnOrder, true)
            ))
        );

        $excelColumns = array_map($makeExcelColumn, $remainingColumnOrder);

        return $table
            ->recordClasses(function (Violation $record): string {
                $classes = 'text-[11px]';
                $displayStatus = self::displayStatus($record);

                if (blank($record->birth_date)) {
                    $classes .= ' violation-missing-birth-date';
                } elseif ($displayStatus === Violation::STATUS_FAILED) {
                    $classes .= ' violation-email-failed';
                }

                return $classes;
            })
            ->columns([
                Tables\Columns\IconColumn::make('status')
                    ->label('')
                    ->alignCenter()
                    ->icons([
                        'heroicon-o-envelope' => static fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_NOT_SENT,
                        'heroicon-o-check-circle' => static fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_SENT,
                        'heroicon-o-x-circle' => static fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_FAILED,
                    ])
                    ->colors([
                        'gray' => static fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_NOT_SENT,
                        'success' => static fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_SENT,
                        'danger' => static fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_FAILED,
                    ])
                    ->disabledClick(fn (Violation $record): bool => self::displayStatus($record) === Violation::STATUS_SENT)
                    ->tooltip(fn (Violation $record): string => match (self::displayStatus($record)) {
                        Violation::STATUS_SENT => __('Sent'),
                        Violation::STATUS_FAILED => __('Failed (click to retry)'),
                        default => __('Send'),
                    })
                    ->action(
                        Action::make('send')
                            ->fillForm(function (Violation $record): array {
                                $draft = app(ViolationEmailSender::class)->buildDraft($record);

                                return [
                                    'subject' => $draft['subject'],
                                    'body' => $draft['body_text'],
                                ];
                            })
                            ->form([
                                Forms\Components\TextInput::make('subject')
                                    ->label('Subject')
                                    ->required()
                                    ->live()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('body')
                                    ->label('Template text (editable)')
                                    ->required()
                                    ->live()
                                    ->rows(14)
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('attachment')
                                    ->label('Attachment (optional)')
                                    ->disk('local')
                                    ->directory('email-attachments')
                                    ->visibility('private')
                                    ->preserveFilenames()
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                        'image/*',
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'text/plain',
                                    ])
                                    ->maxSize(10240),
                                Forms\Components\Placeholder::make('template_preview')
                                    ->label('Full email preview')
                                    ->content(function (Get $get): HtmlString {
                                        $subject = (string) ($get('subject') ?? '');
                                        $body = (string) ($get('body') ?? '');
                                        $html = app(ViolationEmailSender::class)->renderBrandedTemplate($subject, $body);

                                        return new HtmlString(
                                            '<div style="max-height: 360px; overflow: auto; border:1px solid #e5e7eb; border-radius:6px; background:#fff;">'.$html.'</div>'
                                        );
                                    })
                                    ->columnSpanFull(),
                            ])
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
                            ->action(function (Violation $record, array $data): void {
                                try {
                                    $draft = app(ViolationEmailSender::class)->send(
                                        $record,
                                        subjectOverride: (string) ($data['subject'] ?? ''),
                                        bodyOverride: (string) ($data['body'] ?? ''),
                                        attachmentPath: isset($data['attachment']) ? (string) $data['attachment'] : null,
                                    );

                                    $record->update([
                                        'status' => Violation::STATUS_SENT,
                                        'send_error' => null,
                                    ]);
                                    self::recordEmailAttempt($record, $draft, Violation::STATUS_SENT);

                                    Notification::make()
                                        ->success()
                                        ->title('Email sent')
                                        ->body('Notification sent to liumis@gmail.com.')
                                        ->send();
                                } catch (Throwable $e) {
                                    $draft = [
                                        'subject' => (string) ($data['subject'] ?? ''),
                                        'body' => app(ViolationEmailSender::class)->renderBrandedTemplate(
                                            (string) ($data['subject'] ?? ''),
                                            (string) ($data['body'] ?? '')
                                        ),
                                        'body_text' => (string) ($data['body'] ?? ''),
                                        'to' => 'liumis@gmail.com',
                                        'from_email' => '',
                                        'from_name' => '',
                                        'reply_to' => '',
                                    ];

                                    $record->update([
                                        'status' => Violation::STATUS_FAILED,
                                        'send_error' => $e->getMessage(),
                                    ]);
                                    self::recordEmailAttempt($record, $draft, Violation::STATUS_FAILED, $e->getMessage());

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
                Tables\Columns\IconColumn::make('last_email_attempted_at')
                    ->label('Mail')
                    ->alignCenter()
                    ->icons([
                        'heroicon-o-document-magnifying-glass' => static fn (Violation $record): bool => ! blank($record->last_email_attempted_at),
                    ])
                    ->colors([
                        'success' => static fn (Violation $record): bool => $record->last_email_status === Violation::STATUS_SENT,
                        'danger' => static fn (Violation $record): bool => $record->last_email_status === Violation::STATUS_FAILED,
                        'gray' => static fn (Violation $record): bool => blank($record->last_email_attempted_at),
                    ])
                    ->tooltip(static fn (Violation $record): string => blank($record->last_email_attempted_at) ? 'No send attempt yet' : 'View last email attempt')
                    ->disabledClick(static fn (Violation $record): bool => blank($record->last_email_attempted_at))
                    ->action(
                        Action::make('view_last_email_attempt')
                            ->modalHeading('Last email attempt')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->modalContent(static fn (Violation $record): HtmlString => self::emailAttemptModalContent($record))
                    ),
                Tables\Columns\TextColumn::make('import_id')
                    ->label('Import #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('row_number')
                    ->label('Row')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_email_sent_at')
                    ->label('Sent at')
                    ->dateTime('Y-m-d H:i:s')
                    ->timezone('Europe/Vilnius')
                    ->sortable()
                    ->toggleable(),
                ...$afterSentAtExcelColumns,
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
                Tables\Filters\TernaryFilter::make('birth_date')
                    ->label('Birth date')
                    ->nullable()
                    ->trueLabel('Has birth date')
                    ->falseLabel('Missing birth date')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('birth_date')->where('birth_date', '!=', ''),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                            $query->whereNull('birth_date')->orWhere('birth_date', '');
                        }),
                    ),
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
                                        $draft = app(ViolationEmailSender::class)->send($record);

                                        $record->update([
                                            'status' => Violation::STATUS_SENT,
                                            'send_error' => null,
                                        ]);
                                        self::recordEmailAttempt($record, $draft, Violation::STATUS_SENT);

                                        $sentCount++;
                                    } catch (Throwable $e) {
                                        $errorMessage = $e->getMessage();
                                        $draft = [
                                            'subject' => '',
                                            'body' => '',
                                            'body_text' => '',
                                            'to' => 'liumis@gmail.com',
                                            'from_email' => '',
                                            'from_name' => '',
                                            'reply_to' => '',
                                        ];

                                        $record->update([
                                            'status' => Violation::STATUS_FAILED,
                                            'send_error' => $errorMessage,
                                        ]);
                                        self::recordEmailAttempt($record, $draft, Violation::STATUS_FAILED, $errorMessage);

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
     * @param  array<string, string>  $labels
     * @param  list<string>  $defaultVisible
     */
    private static function makeBirthDateColumn(array $labels, array $defaultVisible): Tables\Columns\TextColumn
    {
        $isDefaultVisible = in_array('birth_date', $defaultVisible, true);

        return Tables\Columns\TextColumn::make('birth_date')
            ->label($labels['birth_date'] ?? 'Birth date')
            ->searchable()
            ->sortable()
            ->toggleable($isDefaultVisible === false, isToggledHiddenByDefault: true)
            ->wrap()
            ->date('Y-m-d')
            ->placeholder('—')
            ->color(fn (Violation $record): string => blank($record->birth_date) ? 'danger' : 'gray')
            ->tooltip(fn (Violation $record): ?string => blank($record->birth_date) ? __('Click to add birth date') : null)
            ->disabledClick(fn (Violation $record): bool => filled($record->birth_date))
            ->action(
                Action::make('set_birth_date')
                    ->modalHeading(__('Add birth date'))
                    ->modalSubmitActionLabel(__('Save'))
                    ->form([
                        Forms\Components\DatePicker::make('birth_date')
                            ->label($labels['birth_date'] ?? 'Birth date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->closeOnDateSelection(),
                    ])
                    ->action(function (Violation $record, array $data): void {
                        $record->update([
                            'birth_date' => $data['birth_date'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title(__('Birth date saved'))
                            ->send();
                    })
            );
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

    /**
     * @param array{subject:string,body:string,body_text:string,to:string,from_email:string,from_name:string,reply_to:string} $draft
     */
    private static function recordEmailAttempt(Violation $record, array $draft, string $status, ?string $error = null): void
    {
        $record->forceFill([
            'last_email_subject' => $draft['subject'],
            'last_email_body' => $draft['body'],
            'last_email_to' => $draft['to'],
            'last_email_from' => $draft['from_email'],
            'last_email_reply_to' => $draft['reply_to'],
            'last_email_status' => $status,
            'last_email_error' => $error,
            'last_email_attempted_at' => now(),
            'last_email_sent_at' => $status === Violation::STATUS_SENT ? now() : null,
        ])->save();
    }

    private static function emailAttemptModalContent(Violation $record): HtmlString
    {
        $attemptedAt = $record->last_email_attempted_at?->format('Y-m-d H:i:s') ?? '-';
        $status = (string) ($record->last_email_status ?? '-');
        $to = (string) ($record->last_email_to ?? '-');
        $from = (string) ($record->last_email_from ?? '-');
        $replyTo = (string) ($record->last_email_reply_to ?? '-');
        $subject = (string) ($record->last_email_subject ?? '-');
        $body = (string) ($record->last_email_body ?? '');
        $error = (string) ($record->last_email_error ?? '');

        $errorBlock = $error !== ''
            ? '<br><strong>Error:</strong><br><span style="color:#b91c1c;">'.e($error).'</span>'
            : '';

        return new HtmlString(
            '<strong>Attempted at:</strong> '.e($attemptedAt).'<br>'.
            '<strong>Status:</strong> '.e($status).'<br>'.
            '<strong>To:</strong> '.e($to).'<br>'.
            '<strong>From:</strong> '.e($from).'<br>'.
            '<strong>Reply-To:</strong> '.e($replyTo).'<br>'.
            '<strong>Subject:</strong> '.e($subject).'<br><br>'.
            '<strong>Body:</strong><br><div style="max-height: 360px; overflow:auto; border:1px solid #e5e7eb; padding:10px; border-radius:6px; background:#fff;">'.$body.'</div>'.
            $errorBlock
        );
    }

    private static function displayStatus(Violation $record): string
    {
        if ($record->last_email_status === Violation::STATUS_FAILED) {
            return Violation::STATUS_FAILED;
        }

        if ($record->last_email_status === Violation::STATUS_SENT) {
            return Violation::STATUS_SENT;
        }

        return (string) $record->status;
    }
}
