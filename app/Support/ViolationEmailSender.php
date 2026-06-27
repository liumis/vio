<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\Violation;
use App\Support\MicrosoftGraphMailer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Symfony\Component\Mime\Part\DataPart;

class ViolationEmailSender
{
    private const FIXED_TEST_RECIPIENT = 'liumis@gmail.com';

    /**
     * @return array{subject:string,body:string,body_text:string,to:string,from_email:string,from_name:string,reply_to:string}
     */
    public function buildDraft(Violation $violation): array
    {
        $authorityEmail = trim((string) ($violation->authority_email ?? ''));
        if ($authorityEmail === '') {
            throw new RuntimeException('Authority Email is empty on this violation.');
        }

        $authority = AuthorityMatcher::findForEmail($authorityEmail);

        if (! $authority) {
            $domain = AuthorityMatcher::domainOf($authorityEmail);

            throw new RuntimeException(
                "No authority matches the email pattern for: {$authorityEmail} (domain: {$domain})."
            );
        }

        $template = trim((string) ($authority->mail_template ?? ''));
        if ($template === '') {
            throw new RuntimeException("Mail template is empty for authority: {$authority->name}");
        }

        $bodyText = $this->applyTemplateVariables($template, $violation);
        $settings = EmailSetting::query()->first();

        $subject = trim((string) ($settings?->subject ?? 'Violation notification'));
        $fromEmail = trim((string) config('mail.from.address'));
        $fromName = trim((string) ($settings?->from_name ?? config('mail.from.name')));
        $replyTo = trim((string) env('MAIL_REPLY_TO_ADDRESS', ''));
        $brandedBody = $this->renderBrandedTemplate($subject, $bodyText);

        return [
            'subject' => $subject,
            'body' => $brandedBody,
            'body_text' => $bodyText,
            'to' => self::FIXED_TEST_RECIPIENT,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to' => $replyTo,
        ];
    }

    /**
     * @return array{subject:string,body:string,body_text:string,to:string,from_email:string,from_name:string,reply_to:string}
     */
    public function send(
        Violation $violation,
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
        ?string $attachmentPath = null
    ): array
    {
        $draft = $this->buildDraft($violation);

        if ($subjectOverride !== null) {
            $draft['subject'] = trim($subjectOverride);
        }

        if ($bodyOverride !== null) {
            $draft['body_text'] = $bodyOverride;
        }

        $draft['body'] = $this->renderBrandedTemplate($draft['subject'], $draft['body_text']);

        $this->sendDraft($draft, $attachmentPath);

        return $draft;
    }

    public function renderBrandedTemplate(string $subject, string $contentText, ?string $logoSrc = null): string
    {
        return View::make('emails.violation-branded', [
            'subject' => $subject,
            'contentText' => $contentText,
            'logoSrc' => $logoSrc ?? $this->logoDataUri(),
        ])->render();
    }

    /**
     * @param array{subject:string,body:string,to:string,from_email:string,from_name:string,reply_to:string} $draft
     */
    private function sendDraft(array $draft, ?string $attachmentPath = null): void
    {
        $logoCid = 'sitandgo-logo';
        $htmlForSend = $this->renderBrandedTemplate($draft['subject'], $draft['body_text'], 'cid:'.$logoCid);

        if ($this->useMicrosoftOauthMailer()) {
            app(MicrosoftGraphMailer::class)->sendHtml(
                fromEmail: $draft['from_email'],
                toEmail: $draft['to'],
                subject: $draft['subject'],
                htmlBody: $htmlForSend,
                replyTo: $draft['reply_to'] !== '' ? $draft['reply_to'] : null,
                attachmentPath: $attachmentPath,
                inlineLogoPath: $this->logoFilePath(),
                inlineLogoCid: $logoCid,
            );

            return;
        }

        Mail::html($htmlForSend, function ($message) use ($draft, $attachmentPath, $logoCid): void {
            $message->to($draft['to'])
                ->subject($draft['subject']);

            if ($draft['from_email'] !== '') {
                $message->from($draft['from_email'], $draft['from_name'] !== '' ? $draft['from_name'] : null);
            }

            if ($draft['reply_to'] !== '') {
                $message->replyTo($draft['reply_to']);
            }

            if ($attachmentPath !== null && $attachmentPath !== '' && Storage::disk('local')->exists($attachmentPath)) {
                $message->attachFromStorageDisk('local', $attachmentPath, basename($attachmentPath));
            }

            $logoPath = $this->logoFilePath();
            if ($logoPath !== null) {
                $logoPart = DataPart::fromPath($logoPath);
                $logoPart->asInline();
                $logoPart->setContentId($logoCid);
                $message->getSymfonyMessage()->addPart($logoPart);
            }
        });
    }

    private function applyTemplateVariables(string $template, Violation $violation): string
    {
        return strtr($template, [
            '[%1%]' => (string) ($violation->ticket_number ?? ''),
            '[%2%]' => (string) ($violation->driver ?? ''),
            '[%3%]' => (string) ($violation->ticket_date ?? ''),
            '[%4%]' => (string) ($violation->vehicle ?? ''),
            '[%5%]' => (string) ($violation->vehicle ?? ''),
            '[%6%]' => (string) ($violation->driver ?? ''),
            '[%7%]' => (string) ($violation->birth_date ?? ''),
            '[%8%]' => (string) ($violation->customer_email ?? ''),
            '[%9%]' => (string) ($violation->driver_address ?? ''),
            '[%10%]' => (string) ($violation->driver_telephone ?? ''),
            '[%11%]' => (string) ($violation->licence_issue_date ?? ''),
            '[%12%]' => (string) ($violation->driver_country ?? ''),
        ]);
    }

    private function useMicrosoftOauthMailer(): bool
    {
        return (bool) config('services.microsoft_o365.enabled', false);
    }

    private function logoDataUri(): string
    {
        $path = $this->logoFilePath();
        if ($path === null) {
            return asset('images/brand/sitandgo-logo.png');
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return asset('images/brand/sitandgo-logo.png');
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    private function logoFilePath(): ?string
    {
        $path = public_path('images/brand/sitandgo-logo.png');
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }
}
