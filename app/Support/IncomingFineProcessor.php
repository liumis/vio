<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Saves fine mail into VIO (SharePoint attachments + FineProcessLog).
 * Does not move messages in the mailbox — inbox stays untouched; status lives in DB.
 */
final class IncomingFineProcessor
{
    public function __construct(
        private readonly MicrosoftGraphInbox $inbox = new MicrosoftGraphInbox,
        private readonly MicrosoftGraphSharePoint $sharePoint = new MicrosoftGraphSharePoint,
    ) {
    }

    /**
     * @param  array{
     *     id: string,
     *     subject?: string,
     *     from_email?: string,
     *     received_at?: string,
     *     authority_id?: int|null,
     *     authority_name?: string,
     *     has_attachments?: bool
     * }  $message
     * @return array{
     *     log: FineProcessLog,
     *     attachments_uploaded: int,
     *     sharepoint_urls: list<string>,
     *     moved: bool,
     *     error: string|null
     * }
     */
    public function process(array $message, ?int $userId = null): array
    {
        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId === '') {
            throw new RuntimeException('Message id is missing.');
        }

        $mailbox = $this->inbox->mailbox();
        $subject = trim((string) ($message['subject'] ?? ''));
        $fromEmail = trim((string) ($message['from_email'] ?? ''));
        $authorityName = trim((string) ($message['authority_name'] ?? ''));
        if ($authorityName === '—') {
            $authorityName = '';
        }

        $log = FineProcessLog::query()->firstOrNew(['graph_message_id' => $messageId]);

        $sharePointUrls = is_array($log->sharepoint_urls) ? $log->sharepoint_urls : [];
        $uploaded = (int) $log->attachments_uploaded;
        $error = null;

        // Idempotent: already stored in VIO with attachments (or no attachments expected).
        $alreadyStored = $log->exists
            && filled($log->processed_at)
            && ($uploaded > 0 || empty($message['has_attachments']));

        if (! $alreadyStored) {
            try {
                if ($uploaded === 0 && ! empty($message['has_attachments'])) {
                    $attachments = $this->inbox->listFileAttachments($messageId, $mailbox);

                    foreach ($attachments as $attachment) {
                        $rawName = $this->safeFileName((string) $attachment['name']);
                        $prefix = now()->format('Ymd-His');
                        $authoritySlug = $authorityName !== ''
                            ? Str::slug(Str::limit($authorityName, 40, ''), '-')
                            : 'unknown';
                        $fileName = trim($prefix.'-'.$authoritySlug.'-'.$rawName, '-');

                        $content = $this->inbox->downloadAttachmentContent($messageId, $attachment['id'], $mailbox);
                        $result = $this->sharePoint->upload($fileName, $content);
                        $uploaded++;
                        if ($result['webUrl'] !== '') {
                            $sharePointUrls[] = $result['webUrl'];
                        }
                    }
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $log->fill([
            'mailbox' => $mailbox,
            'from_email' => $fromEmail !== '' ? $fromEmail : $log->from_email,
            'subject' => $subject !== '' ? $subject : $log->subject,
            'received_at' => filled($message['received_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse((string) $message['received_at'])
                : $log->received_at,
            'authority_id' => $message['authority_id'] ?? $log->authority_id,
            'authority_name' => $authorityName !== '' ? $authorityName : $log->authority_name,
            'status' => $error === null ? 'processed' : 'partial',
            'attachments_uploaded' => $uploaded,
            'sharepoint_urls' => $sharePointUrls !== [] ? $sharePointUrls : null,
            // Mail stays in inbox; historical moved_* columns left as-is if previously set.
            'error' => $error,
            'processed_by' => $userId ?? $log->processed_by,
            'processed_at' => $log->processed_at ?? now(),
        ])->save();

        if ($error !== null && $uploaded === 0) {
            throw new RuntimeException($error);
        }

        return [
            'log' => $log,
            'attachments_uploaded' => $uploaded,
            'sharepoint_urls' => $sharePointUrls,
            'moved' => false,
            'error' => $error,
        ];
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN.\-_ ]+/u', '_', $name) ?: 'attachment.bin';
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return 'attachment.bin';
        }

        return Str::limit($name, 120, '');
    }
}
