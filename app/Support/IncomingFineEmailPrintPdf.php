<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\FineProcessLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Instruction (JUDU / Unipark / Parkavimas mieste):
 * violation arrives as email only → Print to PDF → save → SharePoint as "{Driver} {Reference}.pdf".
 */
final class IncomingFineEmailPrintPdf
{
    public const LOCAL_FILE = 'email-print.pdf';

    /**
     * Domains that receive letter-form notices (no official PDF attachment).
     *
     * @var list<string>
     */
    private const LETTER_FORM_PATTERNS = [
        '@judu.lt',
        '@unipark.lt',
        '@parkavimasmieste.lt',
    ];

    public function __construct(
        private readonly MicrosoftGraphInbox $inbox = new MicrosoftGraphInbox,
    ) {
    }

    public function isLetterFormAuthority(?Authority $authority, ?string $fromEmail = null, ?string $authorityName = null): bool
    {
        $pattern = strtolower(trim((string) ($authority?->email_pattern ?? '')));
        foreach (self::LETTER_FORM_PATTERNS as $needle) {
            if ($pattern !== '' && str_contains($pattern, $needle)) {
                return true;
            }
        }

        $from = strtolower(trim((string) $fromEmail));
        foreach (self::LETTER_FORM_PATTERNS as $needle) {
            if ($from !== '' && str_ends_with($from, $needle)) {
                return true;
            }
        }

        $name = mb_strtolower(trim((string) $authorityName), 'UTF-8');
        foreach (['judu', 'unipark', 'parkavimas mieste'] as $needle) {
            if ($name !== '' && str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function shouldPrint(array $message): bool
    {
        $authority = null;
        if (! empty($message['authority_id'])) {
            $authority = Authority::query()->find((int) $message['authority_id']);
        }

        return $this->isLetterFormAuthority(
            $authority,
            isset($message['from_email']) ? (string) $message['from_email'] : null,
            isset($message['authority_name']) ? (string) $message['authority_name'] : null,
        );
    }

    /**
     * Create (or reuse) a local Print-to-PDF of the inbox message.
     *
     * @param  array<string, mixed>  $message
     * @return array{relative_path:string,absolute_path:string,saved_dir:string,bytes:int}|null
     */
    public function ensureLocalPdf(array $message, ?string $savedDir = null): ?array
    {
        if (! $this->shouldPrint($message)) {
            return null;
        }

        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId === '') {
            throw new RuntimeException('Cannot print email to PDF without message id.');
        }

        $dir = trim(str_replace('\\', '/', (string) ($savedDir ?? '')), '/');
        if ($dir === '') {
            $token = Str::limit(preg_replace('/[^A-Za-z0-9_-]+/', '', $messageId) ?: Str::random(12), 40, '');
            $dir = IncomingFineAttachmentTextExtractor::STORAGE_DIR.'/'.now()->format('Ymd-His').'-mailprint-'.$token;
        }

        $relative = $dir.'/'.self::LOCAL_FILE;
        $disk = Storage::disk('local');

        if ($disk->exists($relative) && (int) $disk->size($relative) > 500) {
            return [
                'relative_path' => $relative,
                'absolute_path' => $disk->path($relative),
                'saved_dir' => $dir,
                'bytes' => (int) $disk->size($relative),
            ];
        }

        $disk->makeDirectory($dir);

        $htmlBody = '';
        $textBody = '';
        try {
            $htmlBody = $this->inbox->getMessageBodyHtml($messageId);
        } catch (Throwable) {
            $htmlBody = '';
        }
        try {
            $textBody = $this->inbox->getMessageBodyText($messageId);
        } catch (Throwable) {
            $textBody = trim((string) ($message['preview'] ?? ''));
        }

        if (trim(strip_tags($htmlBody)) === '' && trim($textBody) === '') {
            $textBody = trim((string) ($message['preview'] ?? ''));
        }
        if (trim(strip_tags($htmlBody)) === '' && trim($textBody) === '') {
            throw new RuntimeException('Email body is empty — cannot Print to PDF.');
        }

        $isHtml = trim(strip_tags($htmlBody)) !== '';
        $safeHtml = $isHtml ? $this->sanitizeHtml($htmlBody) : '';

        $from = trim((string) ($message['from_name'] ?? ''));
        $fromEmail = trim((string) ($message['from_email'] ?? ''));
        if ($from !== '' && $fromEmail !== '') {
            $fromLabel = $from.' <'.$fromEmail.'>';
        } else {
            $fromLabel = $from !== '' ? $from : ($fromEmail !== '' ? $fromEmail : '—');
        }

        $payload = [
            'printedAt' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'from' => $fromLabel,
            'to' => $this->inbox->mailbox(),
            'subject' => trim((string) ($message['subject'] ?? '')) ?: '(no subject)',
            'receivedAt' => $this->formatReceived($message['received_at'] ?? null),
            'isHtml' => $isHtml,
            'bodyHtml' => $safeHtml,
            'bodyText' => $textBody,
        ];

        $binary = Pdf::loadView('pdfs.email-print', $payload)->setPaper('a4')->output();
        if ($binary === '' || $binary === false) {
            throw new RuntimeException('Dompdf returned an empty email-print PDF.');
        }

        $disk->put($relative, $binary);

        return [
            'relative_path' => $relative,
            'absolute_path' => $disk->path($relative),
            'saved_dir' => $dir,
            'bytes' => strlen($binary),
        ];
    }

    /**
     * Ensure local email PDF exists, then archive to SharePoint as "{Driver} {Reference}.pdf".
     *
     * @param  array<string, mixed>  $message
     */
    public function ensureAndArchive(FineProcessLog $log, array $message = []): ?string
    {
        $payload = $message !== [] ? $message : [
            'id' => $log->graph_message_id,
            'subject' => $log->subject,
            'from_email' => $log->from_email,
            'authority_id' => $log->authority_id,
            'authority_name' => $log->authority_name,
            'received_at' => optional($log->received_at)?->toIso8601String(),
            'preview' => $log->subject,
        ];

        if (! $this->shouldPrint($payload) && ! $this->isLetterFormAuthority(
            $log->authority,
            $log->from_email,
            $log->authority_name,
        )) {
            return null;
        }

        // Force letter-form detection for ensureLocalPdf when only log fields are available.
        if (empty($payload['authority_id']) && $log->authority_id) {
            $payload['authority_id'] = $log->authority_id;
        }
        if (empty($payload['from_email']) && $log->from_email) {
            $payload['from_email'] = $log->from_email;
        }

        $local = $this->ensureLocalPdf($payload, $log->local_attachment_dir);
        if ($local === null) {
            return null;
        }

        if ($log->local_attachment_dir !== $local['saved_dir']) {
            $log->fill(['local_attachment_dir' => $local['saved_dir']])->save();
        }

        return app(IncomingFineDocumentArchiver::class)->archive($log->fresh(), forceReupload: false);
    }

    private function formatReceived(mixed $receivedAt): string
    {
        if ($receivedAt === null || $receivedAt === '') {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $receivedAt)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d H:i');
        } catch (Throwable) {
            return (string) $receivedAt;
        }
    }

    private function sanitizeHtml(string $html): string
    {
        // Drop scripts/styles that Dompdf does not need; keep basic formatting.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/on\w+="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/on\w+=\'[^\']*\'/i', '', $html) ?? $html;

        return $html;
    }
}
