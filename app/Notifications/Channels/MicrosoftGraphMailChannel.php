<?php

namespace App\Notifications\Channels;

use App\Models\EmailSetting;
use App\Support\MicrosoftGraphMailer;
use Illuminate\Notifications\Notification;
use RuntimeException;

class MicrosoftGraphMailChannel
{
    public function __construct(
        private readonly MicrosoftGraphMailer $mailer = new MicrosoftGraphMailer,
    ) {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toMicrosoftGraph')) {
            throw new RuntimeException(sprintf(
                'Notification [%s] is missing toMicrosoftGraph().',
                $notification::class,
            ));
        }

        /** @var array{to:string,subject:string,html:string,reply_to?:string|null} $payload */
        $payload = $notification->toMicrosoftGraph($notifiable);

        $config = EmailSetting::graphConfig();
        $fromEmail = $config['from_email'];
        if ($fromEmail === '') {
            throw new RuntimeException('From email is empty. Set it in System settings.');
        }

        $to = trim((string) ($payload['to'] ?? ''));
        if ($to === '') {
            $to = (string) $notifiable->routeNotificationFor('mail', $notification);
        }

        if ($to === '') {
            throw new RuntimeException('Password reset recipient email is empty.');
        }

        $logoPath = public_path('images/brand/sitandgo-logo.png');

        $this->mailer->sendHtml(
            fromEmail: $fromEmail,
            toEmail: $to,
            subject: (string) ($payload['subject'] ?? 'Reset your Sit&Go password'),
            htmlBody: (string) ($payload['html'] ?? ''),
            replyTo: $payload['reply_to'] ?? ($config['reply_to'] !== '' ? $config['reply_to'] : null),
            inlineLogoPath: is_file($logoPath) ? $logoPath : null,
            inlineLogoCid: 'sitandgo-logo',
        );
    }
}
