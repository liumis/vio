<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuthorityResource\Pages;
use App\Models\Authority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuthorityResource extends Resource
{
    protected static ?string $model = Authority::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Authorities';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('from_email')
                ->email()
                ->required()
                ->label('From email')
                ->maxLength(255),
            Forms\Components\TextInput::make('main_email')
                ->email()
                ->required()
                ->label('Main email')
                ->maxLength(255),
            Forms\Components\TextInput::make('to_email')
                ->email()
                ->required()
                ->label('To email')
                ->maxLength(255),
            Forms\Components\TextInput::make('email_pattern')
                ->label('Email pattern')
                ->maxLength(255),
            Forms\Components\Textarea::make('mail_template')
                ->required()
                ->label('Mail template')
                ->rows(8)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('informavimo_terminas')
                ->label('Informavimo terminas')
                ->numeric()
                ->minValue(1)
                ->maxValue(365)
                ->nullable()
                ->helperText('Dienų skaičius nuo laiško / dokumento gavimo (instrukcija: 5 darbo dienos).'),
            Forms\Components\Select::make('informavimo_terminas_tipas')
                ->label('Termino tipas')
                ->options([
                    'working' => 'Darbo dienos',
                    'calendar' => 'Kalendorinės dienos',
                ])
                ->default('working')
                ->required(),
            Forms\Components\Select::make('info_pdf_template')
                ->label('Info PDF šablonas')
                ->options([
                    'vilnius' => 'Vilnius / savivaldybė (oficialus pranešimas)',
                    'latvia' => 'Latvija CPP (pranešimas + Marksign)',
                ])
                ->nullable()
                ->helperText('Jei pasirinkta — siunčiant laišką generuojamas PDF pavadinimu = registracijos Nr. (pvz. A51-56775.pdf) ir prisegamas kartu su gautu pranešimu. JUDU/Unipark/Julianus — palikti tuščią.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_email')
                    ->label('From email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('main_email')
                    ->label('Main email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_email')
                    ->label('To email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_pattern')
                    ->label('Email pattern')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mail_template')
                    ->label('Mail template')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (Authority $record): string => (string) $record->mail_template),
                Tables\Columns\TextColumn::make('informavimo_terminas')
                    ->label('Informavimo terminas')
                    ->formatStateUsing(function (?int $state, Authority $record): string {
                        if ($state === null) {
                            return '—';
                        }
                        $tipas = ($record->informavimo_terminas_tipas ?? 'working') === 'calendar'
                            ? 'kal. d.'
                            : 'd.d.';

                        return $state.' '.$tipas;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('info_pdf_template')
                    ->label('Info PDF')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'vilnius' => 'Vilnius',
                        'latvia' => 'Latvija',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListAuthorities::route('/'),
            'create' => Pages\CreateAuthority::route('/create'),
            'edit' => Pages\EditAuthority::route('/{record}/edit'),
        ];
    }
}
