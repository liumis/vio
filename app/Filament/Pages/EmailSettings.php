<?php

namespace App\Filament\Pages;

use App\Models\EmailSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class EmailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email settings';

    protected static ?int $navigationSort = 25;

    protected static string $view = 'filament.pages.email-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = EmailSetting::query()->first();

        $this->form->fill([
            'from_name' => $settings?->from_name,
            'from_email' => $settings?->from_email,
            'reply_to' => $settings?->reply_to,
            'subject' => $settings?->subject,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('from_name')
                    ->label('From')
                    ->maxLength(255),
                Forms\Components\TextInput::make('from_email')
                    ->label('From email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reply_to')
                    ->label('Reply-to')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('subject')
                    ->label('Subject')
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        EmailSetting::query()->updateOrCreate(
            ['id' => 1],
            $this->form->getState()
        );

        Notification::make()
            ->success()
            ->title('Email settings saved')
            ->send();
    }
}
