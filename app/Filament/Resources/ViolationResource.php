<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ViolationResource\Pages;
use App\Models\FineProcessLog;
use App\Support\ChargebackNoticeService;
use App\Support\DebtTrackerService;
use App\Support\FineReplyReadiness;
use App\Support\IncomingFineReplyBuilder;
use App\Support\IncomingFineReplySender;
use App\Support\InformavimoTerminas;
use App\Support\WheelsysLoginBridge;
use App\Support\WheelsysRentalDocumentArchiver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Overview of fines that went through the Wheelsys import path.
 */
class ViolationResource extends Resource
{
    protected static ?string $model = FineProcessLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = '3. Imported';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $slug = 'violations';

    protected static ?string $modelLabel = 'imported';

    protected static ?string $pluralModelLabel = 'Imported';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('wheels_export_status', FineProcessLog::WHEELS_EXPORT_EXPORTED)
            ->with(['exporter', 'importer', 'authority']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('wheels_imported_at', 'desc')
            ->defaultGroup('wheels_imported_at')
            ->groupingSettingsInDropdownOnDesktop()
            ->groups([
                Group::make('wheels_imported_at')
                    ->label('Imported')
                    ->date()
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
                Group::make('wheels_export_batch')
                    ->label('Import file')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(function (FineProcessLog $record): string {
                        $file = trim((string) ($record->wheels_export_file_name ?: ''));
                        if ($file !== '') {
                            return $file;
                        }
                        $batch = trim((string) ($record->wheels_export_batch ?: ''));

                        return $batch !== '' ? $batch : 'No import file';
                    })
                    ->getDescriptionFromRecordUsing(function (FineProcessLog $record): ?string {
                        if (! $record->wheels_imported_at) {
                            return null;
                        }

                        return 'Imported '.$record->wheels_imported_at
                            ->timezone(config('app.timezone'))
                            ->format('Y-m-d H:i');
                    }),
            ])
            ->columns([
                Tables\Columns\ViewColumn::make('reply_ready')
                    ->label('Reply')
                    ->view('filament.tables.columns.reply-readiness-bubble'),
                Tables\Columns\ViewColumn::make('mail_preview')
                    ->label('Preview / Send')
                    ->view('filament.tables.columns.mail-preview-icon')
                    ->alignStart(),
                Tables\Columns\TextColumn::make('email_to_authority')
                    ->label('Email to Authority')
                    ->badge()
                    ->state(function (FineProcessLog $record): string {
                        if (FineReplyReadiness::forLog($record)['channel'] === 'epaslaugos') {
                            return 'Not needed';
                        }

                        return filled($record->email_sent_at) ? 'Sent' : 'Not sent';
                    })
                    ->color(function (FineProcessLog $record): string {
                        if (FineReplyReadiness::forLog($record)['channel'] === 'epaslaugos') {
                            return 'gray';
                        }

                        return filled($record->email_sent_at) ? 'success' : 'danger';
                    })
                    ->weight(fn (FineProcessLog $record): string => filled($record->email_sent_at) ? 'medium' : 'bold')
                    ->tooltip(function (FineProcessLog $record): ?string {
                        if (FineReplyReadiness::forLog($record)['channel'] === 'epaslaugos') {
                            return 'ANR / ePaslaugos — no authority email';
                        }
                        if (! filled($record->email_sent_at)) {
                            return 'Authority not informed yet — send reply';
                        }
                        $when = $record->email_sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i');
                        $to = trim((string) ($record->email_to ?? ''));

                        return $to !== '' ? "Sent {$when} → {$to}" : "Sent {$when}";
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('email_sent_at', $direction);
                    }),
                Tables\Columns\TextColumn::make('wheels_import_status')
                    ->label('Wheels')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        FineProcessLog::WHEELS_IMPORT_IMPORTED => 'Imported',
                        FineProcessLog::WHEELS_IMPORT_MISSING => 'Missing',
                        FineProcessLog::WHEELS_IMPORT_AWAITING, null, '' => 'Awaiting',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        FineProcessLog::WHEELS_IMPORT_IMPORTED => 'success',
                        FineProcessLog::WHEELS_IMPORT_MISSING => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('wheels_imported_at')
                    ->label('Imported date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wheels_export_file_name')
                    ->label('Import file')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('—')
                    ->description(fn (FineProcessLog $record): ?string => filled($record->wheels_exported_at)
                        ? 'Prepared '.$record->wheels_exported_at->timezone(config('app.timezone'))->format('Y-m-d H:i')
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('agr_no')
                    ->label('Contract')
                    ->searchable()
                    ->placeholder(fn (FineProcessLog $record): string => ($record->authority?->requiresContract() ? 'RNT missing' : '—'))
                    ->color(fn (FineProcessLog $record): ?string => filled($record->agr_no)
                        ? 'success'
                        : ($record->authority?->requiresContract() ? 'danger' : null))
                    ->weight(fn (FineProcessLog $record): string => ($record->authority?->requiresContract() && ! filled($record->agr_no))
                        ? 'bold'
                        : 'medium')
                    ->tooltip(fn (FineProcessLog $record): ?string => ($record->authority?->requiresContract() && ! filled($record->agr_no))
                        ? 'Julianus requires rental contract (RNT)'
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ticket_date')
                    ->label('Ticket date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('authority_name')
                    ->label('Authority')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('informavimo')
                    ->label('Informavimas')
                    ->state(fn (FineProcessLog $record): string => InformavimoTerminas::forLog($record)['label'])
                    ->badge()
                    ->color(fn (FineProcessLog $record): string => match (InformavimoTerminas::forLog($record)['level']) {
                        InformavimoTerminas::LEVEL_WARN => 'warning',
                        InformavimoTerminas::LEVEL_CRITICAL => 'danger',
                        InformavimoTerminas::LEVEL_DONE => 'success',
                        InformavimoTerminas::LEVEL_OK => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(function (FineProcessLog $record): ?string {
                        $due = InformavimoTerminas::forLog($record)['due_date'] ?? null;

                        return $due ? 'Terminas: '.$due : null;
                    }),
                Tables\Columns\TextColumn::make('wheels_violation_id')
                    ->label('Wheels TV')
                    ->placeholder('—')
                    ->url(fn (FineProcessLog $record): ?string => filled($record->wheels_violation_id)
                        ? WheelsysLoginBridge::trafficViolationUrl((string) $record->wheels_violation_id)
                        : null)
                    ->openUrlInNewTab()
                    ->color(fn (FineProcessLog $record): ?string => filled($record->wheels_violation_id) ? 'primary' : null)
                    ->tooltip(fn (FineProcessLog $record): ?string => filled($record->wheels_violation_id)
                        ? 'Open in Wheelsys'
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wheels_verify_detail')
                    ->label('Detail')
                    ->wrap()
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('chargeback_status')
                    ->label('Chargeback')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        FineProcessLog::CHARGEBACK_AWAITING_SIGNATURE => 'Awaiting MarkSign',
                        FineProcessLog::CHARGEBACK_SIGNED => 'Signed',
                        FineProcessLog::CHARGEBACK_NOTICE_SENT => 'Notice sent',
                        default => $state ? ucfirst((string) $state) : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        FineProcessLog::CHARGEBACK_AWAITING_SIGNATURE => 'warning',
                        FineProcessLog::CHARGEBACK_SIGNED => 'success',
                        FineProcessLog::CHARGEBACK_NOTICE_SENT => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Mail / process')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('wheels_import_status')
                    ->label('Wheels status')
                    ->options([
                        FineProcessLog::WHEELS_IMPORT_AWAITING => 'Awaiting',
                        FineProcessLog::WHEELS_IMPORT_MISSING => 'Missing',
                        FineProcessLog::WHEELS_IMPORT_IMPORTED => 'Imported',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === null || $value === '') {
                            return $query;
                        }
                        if ($value === FineProcessLog::WHEELS_IMPORT_AWAITING) {
                            return $query->where(function (Builder $q): void {
                                $q->whereNull('wheels_import_status')
                                    ->orWhere('wheels_import_status', FineProcessLog::WHEELS_IMPORT_AWAITING);
                            });
                        }

                        return $query->where('wheels_import_status', $value);
                    }),
                Tables\Filters\SelectFilter::make('reply_mail')
                    ->label('Email to Authority')
                    ->options([
                        'sent' => 'Sent',
                        'not_sent' => 'Not sent',
                        'not_needed' => 'Not needed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === 'sent') {
                            return $query->whereNotNull('email_sent_at');
                        }
                        if ($value === 'not_sent') {
                            return $query->whereNull('email_sent_at')
                                ->whereDoesntHave('authority', function (Builder $q): void {
                                    $q->where('email_pattern', 'like', '%anr.gov%');
                                });
                        }
                        if ($value === 'not_needed') {
                            return $query->whereHas('authority', function (Builder $q): void {
                                $q->where('email_pattern', 'like', '%anr.gov%');
                            });
                        }

                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('chargeback_status')
                    ->label('Chargeback')
                    ->options([
                        FineProcessLog::CHARGEBACK_AWAITING_SIGNATURE => 'Awaiting MarkSign',
                        FineProcessLog::CHARGEBACK_SIGNED => 'Signed',
                        FineProcessLog::CHARGEBACK_NOTICE_SENT => 'Notice sent',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('openSharePoint')
                    ->label('Document')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->url(fn (FineProcessLog $record): ?string => $record->documentSharePointUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (FineProcessLog $record): bool => filled($record->documentSharePointUrl()))
                    ->tooltip('Document · Fine PDF on SharePoint'),
                Tables\Actions\Action::make('fetchRntDocs')
                    ->label('RNT docs')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Fetch RNT attachments from Wheelsys → SharePoint')
                    ->visible(fn (FineProcessLog $record): bool => filled($record->rental_id))
                    ->requiresConfirmation()
                    ->modalHeading('Fetch RNT documents')
                    ->modalDescription('Downloads Attached documents from Wheelsys rental and saves them into the fine SharePoint folder (…/{Reference}__{RNT}/).')
                    ->action(function (FineProcessLog $record): void {
                        try {
                            $result = app(WheelsysRentalDocumentArchiver::class)->archiveForLog($record, force: true);
                            Notification::make()
                                ->success()
                                ->title('RNT docs saved')
                                ->body($result['count'].' file(s) → '.$result['folder'])
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('RNT fetch failed')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('chargeFailed')
                    ->label('Nepavyko nuskaičiuoti')
                    ->icon('heroicon-o-banknotes')
                    ->iconButton()
                    ->color('danger')
                    ->tooltip('Nepavyko nuskaičiuoti')
                    ->modalHeading('IV · Veiksmai po nepavykusio nuskaičiavimo')
                    ->modalDescription('Sugeneruos dvikalbį pranešimą (Priedas Nr. 3), įkels į MarkSign ir nusiųs pasirašyti Mykolui.')
                    ->modalSubmitActionLabel('Generuoti + MarkSign')
                    ->fillForm(function (FineProcessLog $record): array {
                        $data = is_array($record->chargeback_data) ? $record->chargeback_data : [];

                        return [
                            'client_name' => $data['client_name'] ?? $record->driver_name,
                            'client_email' => $data['client_email'] ?? '',
                            'rental_no' => $data['rental_no'] ?? $record->agr_no,
                            'reference' => $data['reference'] ?? $record->reference,
                            'fine_amount' => $data['fine_amount'] ?? null,
                            'admin_fee' => $data['admin_fee'] ?? 72.60,
                            'admin_fee_letter_date' => $data['admin_fee_letter_date'] ?? null,
                            'company_paid_fine' => $data['company_paid_fine'] ?? true,
                            'inv_number' => $data['inv_number'] ?? null,
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('client_name')
                            ->label('Klientas (vardas / įmonė)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_email')
                            ->label('Kliento el. paštas')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('rental_no')
                            ->label('Nuomos sutartis (RNT)')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('reference')
                            ->label('Pažeidimo / bylos Nr.')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('inv_number')
                            ->label('INV numeris (Finvalda)')
                            ->maxLength(64)
                            ->helperText('Reikalingas LT debt registracijai po laiško išsiuntimo.'),
                        Forms\Components\TextInput::make('fine_amount')
                            ->label('Baudos suma (EUR)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('admin_fee')
                            ->label('Administracinis mokestis (EUR)')
                            ->numeric()
                            ->default(72.60)
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Standartas 72,60 EUR (su PVM).'),
                        Forms\Components\DatePicker::make('admin_fee_letter_date')
                            ->label('Adm. mokesčio laiško data')
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                        Forms\Components\Toggle::make('company_paid_fine')
                            ->label('Įmonė jau sumokėjo baudą už klientą')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->action(function (FineProcessLog $record, array $data): void {
                        try {
                            $result = app(ChargebackNoticeService::class)->markFailedAndSendToMarksign($record, $data);
                            Notification::make()
                                ->success()
                                ->title('Pranešimas MarkSign’e')
                                ->body('UUID: '.$result['marksign_uuid'].' · statusas: Awaiting MarkSign. Pasirašytojas gaus nuorodą.')
                                ->persistent()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Nepavyko')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('openMarksign')
                    ->label('MarkSign link')
                    ->icon('heroicon-o-link')
                    ->iconButton()
                    ->url(fn (FineProcessLog $record): ?string => $record->marksign_signing_url)
                    ->openUrlInNewTab()
                    ->visible(fn (FineProcessLog $record): bool => filled($record->marksign_signing_url))
                    ->color('warning')
                    ->tooltip('MarkSign link'),
                Tables\Actions\Action::make('registerLtDebt')
                    ->label('Registruoti LT debt')
                    ->icon('heroicon-o-table-cells')
                    ->iconButton()
                    ->color('success')
                    ->tooltip('Registruoti LT debt')
                    ->visible(fn (FineProcessLog $record): bool => filled($record->chargeback_status)
                        && $record->chargeback_status !== FineProcessLog::CHARGEBACK_NOTICE_SENT)
                    ->modalHeading('Registruoti CHARGEBACKS/DEBTS → LT debt')
                    ->modalDescription('Pagal instrukciją po laiško klientui. Įrašoma į SharePoint darbinę kopiją.')
                    ->modalSubmitActionLabel('Įrašyti eilutę')
                    ->fillForm(function (FineProcessLog $record): array {
                        $data = is_array($record->chargeback_data) ? $record->chargeback_data : [];

                        return [
                            'inv_number' => $data['inv_number'] ?? '',
                            'comment' => $data['comment'] ?? '',
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('inv_number')
                            ->label('INV numeris')
                            ->required()
                            ->maxLength(64),
                        Forms\Components\TextInput::make('comment')
                            ->label('Komentaras')
                            ->maxLength(255),
                    ])
                    ->action(function (FineProcessLog $record, array $data): void {
                        try {
                            $result = app(DebtTrackerService::class)->registerSentNotice(
                                $record,
                                $data['inv_number'] ?? null,
                                $data['comment'] ?? null,
                            );
                            Notification::make()
                                ->success()
                                ->title('LT debt eilutė įrašyta')
                                ->body('Row '.$result['row'].($result['sharepoint_url'] !== '' ? ' · '.$result['sharepoint_url'] : ''))
                                ->persistent()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('LT debt registracija nepavyko')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('openDebtTracker')
                    ->label('LT debt failas')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->iconButton()
                    ->url(fn (): ?string => DebtTrackerService::config()['sharepoint_url']
                        ?: DebtTrackerService::config()['google_url']
                        ?: null)
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled(DebtTrackerService::config()['sharepoint_url'])
                        || filled(DebtTrackerService::config()['google_url']))
                    ->color('gray')
                    ->tooltip('LT debt failas'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('sendReadyReplies')
                        ->label('Send replies (ready only)')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk send authority replies')
                        ->modalDescription('Sends only rows with a green Reply bubble (full driver/template info). Skips Missing, Sent, and ePaslaugos.')
                        ->modalSubmitActionLabel('Send ready')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $sent = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof FineProcessLog) {
                                    $skipped++;
                                    continue;
                                }

                                $wheelsys = FineReplyReadiness::enrichAndStore($record);
                                $info = FineReplyReadiness::forLog($record->fresh(), $wheelsys);

                                if ($info['sent'] || ! $info['ready'] || $info['channel'] === 'epaslaugos') {
                                    $skipped++;
                                    continue;
                                }

                                try {
                                    $draft = app(IncomingFineReplyBuilder::class)
                                        ->buildForProcessLog($record->fresh(), $wheelsys);
                                    if (($draft['reply_channel'] ?? '') === 'epaslaugos') {
                                        $skipped++;
                                        continue;
                                    }
                                    if (! empty($draft['missing_fields'])) {
                                        $skipped++;
                                        continue;
                                    }

                                    $log = app(IncomingFineReplySender::class)->send($draft, $record);
                                    $workflow = is_array($log->workflow) ? $log->workflow : [];
                                    $workflow['wheelsys'] = $wheelsys;
                                    $workflow['steps'] = array_values(array_merge(
                                        is_array($workflow['steps'] ?? null) ? $workflow['steps'] : [],
                                        [['key' => 'authority_reply', 'ok' => true, 'detail' => 'Bulk sent to '.$draft['to']]]
                                    ));
                                    $log->fill(['workflow' => $workflow])->save();
                                    $sent++;
                                } catch (Throwable) {
                                    $failed++;
                                }
                            }

                            $notification = Notification::make()
                                ->title('Bulk send finished')
                                ->body("Sent {$sent} · skipped {$skipped} · failed {$failed}");

                            if ($sent > 0 && $failed === 0) {
                                $notification->success();
                            } elseif ($sent > 0) {
                                $notification->warning();
                            } else {
                                $notification->danger();
                            }
                            $notification->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No imported fines yet')
            ->emptyStateDescription('Prepare a file in To Wheelsys, import in Wheelsys, then Update Wheelsys status.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListViolations::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }
}
