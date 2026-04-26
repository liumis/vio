<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\EmailSetting;
use App\Models\Violation;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ViolationEmailSender
{
    private const FIXED_TEST_RECIPIENT = 'liumis@gmail.com';

    public function send(Violation $violation): void
    {
        $authorityEmail = trim((string) ($violation->authority_email ?? ''));
        if ($authorityEmail === '') {
            throw new RuntimeException('Authority Email is empty on this violation.');
        }

        $authority = Authority::query()
            ->whereRaw('LOWER(main_email) = ?', [mb_strtolower($authorityEmail)])
            ->first();

        if (! $authority) {
            throw new RuntimeException("No authority found for Authority Email: {$authorityEmail}");
        }

        $template = trim((string) ($authority->mail_template ?? ''));
        if ($template === '') {
            throw new RuntimeException("Mail template is empty for authority: {$authority->name}");
        }

        $body = $this->applyTemplateVariables($template, $violation);
        $settings = EmailSetting::query()->first();

        $subject = trim((string) ($settings?->subject ?? 'Violation notification'));
        $fromEmail = trim((string) ($settings?->from_email ?? config('mail.from.address')));
        $fromName = trim((string) ($settings?->from_name ?? config('mail.from.name')));
        $replyTo = trim((string) ($settings?->reply_to ?? ''));

        Mail::html($body, function ($message) use ($subject, $fromEmail, $fromName, $replyTo): void {
            $message->to(self::FIXED_TEST_RECIPIENT)
                ->subject($subject);

            if ($fromEmail !== '') {
                $message->from($fromEmail, $fromName !== '' ? $fromName : null);
            }

            if ($replyTo !== '') {
                $message->replyTo($replyTo);
            }
        });
    }

    private function applyTemplateVariables(string $template, Violation $violation): string
    {
        return strtr($template, [
            '[%1%]' => (string) ($violation->ticket_number ?? ''),
            '[%2%]' => (string) ($violation->driver ?? ''),
            '[%3%]' => (string) ($violation->ticket_date ?? ''),
            '[%5%]' => (string) ($violation->vehicle ?? ''),
            '[%7%]' => (string) ($violation->birth_date ?? ''),
            '[%8%]' => (string) ($violation->customer_email ?? ''),
            '[%9%]' => (string) ($violation->driver_address ?? ''),
            '[%10%]' => (string) ($violation->driver_telephone ?? ''),
            '[%11%]' => (string) ($violation->licence_issue_date ?? ''),
            '[%12%]' => (string) ($violation->driver_country ?? ''),
        ]);
    }
}
