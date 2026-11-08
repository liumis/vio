<?php

namespace App\Notifications;

use App\Models\EmailSetting;
use App\Notifications\Channels\MicrosoftGraphMailChannel;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\View;

/**
 * Used by Filament password-reset (bound over Filament\Notifications\Auth\ResetPassword).
 * Sends via Microsoft Graph when O365 is enabled; otherwise falls back to Laravel mail.
 * Not queued — Cloud often has no queue worker, so reset mail must send inline.
 */
class CustomResetPasswordNotification extends ResetPassword
{
    public string $url = '';

    /**
     * @return list<string|class-string>
     */
    public function via($notifiable): array
    {
        if (EmailSetting::graphConfig()['enabled']) {
            return [MicrosoftGraphMailChannel::class];
        }

        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your Sit&Go password')
            ->view('emails.password-reset', $this->viewData($notifiable, asset('images/brand/sitandgo-logo.png')));
    }

    /**
     * @return array{to:string,subject:string,html:string,reply_to:?string}
     */
    public function toMicrosoftGraph(object $notifiable): array
    {
        $config = EmailSetting::graphConfig();

        return [
            'to' => method_exists($notifiable, 'getEmailForPasswordReset')
                ? (string) $notifiable->getEmailForPasswordReset()
                : (string) ($notifiable->email ?? ''),
            'subject' => 'Reset your Sit&Go password',
            'html' => View::make('emails.password-reset', $this->viewData($notifiable, 'cid:sitandgo-logo'))->render(),
            'reply_to' => $config['reply_to'] !== '' ? $config['reply_to'] : null,
        ];
    }

    protected function resetUrl(mixed $notifiable): string
    {
        if ($this->url !== '') {
            return $this->url;
        }

        try {
            return Filament::getResetPasswordUrl($this->token, $notifiable);
        } catch (\Throwable) {
            return url(route('filament.admin.auth.password-reset.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        }
    }

    /**
     * @return array{subject:string,recipientName:?string,resetUrl:string,expireMinutes:int|mixed,logoSrc:string}
     */
    private function viewData(object $notifiable, string $logoSrc): array
    {
        return [
            'subject' => 'Reset your Sit&Go password',
            'recipientName' => $notifiable->name ?? null,
            'resetUrl' => $this->resetUrl($notifiable),
            'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            'logoSrc' => $logoSrc,
        ];
    }
}
