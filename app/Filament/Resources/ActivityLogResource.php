<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use App\Models\Violation;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Logs';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('id')
                    ->label('')
                    ->icon(function (ActivityLog $record): ?string {
                        $action = mb_strtolower((string) $record->action);

                        if (str_contains($action, 'violation email success')) {
                            return 'heroicon-o-check-circle';
                        }

                        if (str_contains($action, 'violation email fail')) {
                            return 'heroicon-o-x-circle';
                        }

                        if (str_contains($action, 'auth: failed login')) {
                            return 'heroicon-o-exclamation-triangle';
                        }

                        return null;
                    })
                    ->color(function (ActivityLog $record): ?string {
                        $action = mb_strtolower((string) $record->action);

                        if (str_contains($action, 'violation email success')) {
                            return 'success';
                        }

                        if (str_contains($action, 'violation email fail')) {
                            return 'danger';
                        }

                        if (str_contains($action, 'auth: failed login')) {
                            return 'warning';
                        }

                        return null;
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->timezone('Europe/Vilnius')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Username')
                    ->default('-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->wrap()
                    ->url(fn (ActivityLog $record): ?string => self::violationIndexUrl($record), shouldOpenInNewTab: true)
                    ->color(fn (ActivityLog $record): ?string => self::violationIndexUrl($record) ? 'primary' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Type')
                    ->options([
                        'email_success' => 'Email success',
                        'email_fail' => 'Email failed',
                        'login_fail' => 'Login failed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'email_success' => $query->where('action', 'like', 'Violation email SUCCESS:%'),
                            'email_fail' => $query->where('action', 'like', 'Violation email FAIL:%'),
                            'login_fail' => $query->where('action', 'like', 'Auth: failed login%'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('user_name')
                    ->label('Username')
                    ->options(fn (): array => ActivityLog::query()
                        ->whereNotNull('user_name')
                        ->distinct()
                        ->orderBy('user_name')
                        ->pluck('user_name', 'user_name')
                        ->all()),
                Tables\Filters\Filter::make('time_range')
                    ->label('Time range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_sent_email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope-open')
                    ->visible(fn (ActivityLog $record): bool => self::relatedViolation($record) !== null)
                    ->modalHeading('Email from this log entry')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (ActivityLog $record): HtmlString => self::sentEmailModalContent($record)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }

    private static function relatedViolation(ActivityLog $record): ?Violation
    {
        $violationId = (int) ($record->meta['violation_id'] ?? 0);
        if ($violationId <= 0) {
            return null;
        }

        return Violation::query()->find($violationId);
    }

    private static function violationIndexUrl(ActivityLog $record): ?string
    {
        $violation = self::relatedViolation($record);
        if (! $violation) {
            return null;
        }

        return ViolationResource::getUrl('index', ['import_id' => $violation->import_id]);
    }

    private static function sentEmailModalContent(ActivityLog $record): HtmlString
    {
        $violation = self::relatedViolation($record);

        if (! $violation) {
            return new HtmlString('<em>Violation not found for this log entry.</em>');
        }

        $status = (string) ($violation->last_email_status ?? '-');
        $sentAt = $violation->last_email_sent_at?->timezone('Europe/Vilnius')->format('Y-m-d H:i:s') ?? '-';
        $to = (string) ($violation->last_email_to ?? '-');
        $subject = (string) ($violation->last_email_subject ?? '-');
        $body = (string) ($violation->last_email_body ?? '');

        return new HtmlString(
            '<strong>Status:</strong> '.e($status).'<br>'.
            '<strong>Sent at:</strong> '.e($sentAt).'<br>'.
            '<strong>To:</strong> '.e($to).'<br>'.
            '<strong>Subject:</strong> '.e($subject).'<br><br>'.
            '<strong>Body:</strong><br><div style="max-height: 360px; overflow:auto; border:1px solid #e5e7eb; padding:10px; border-radius:6px; background:#fff;">'.$body.'</div>'
        );
    }
}
