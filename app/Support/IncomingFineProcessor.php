<?php

namespace App\Support;

use App\Models\FineProcessLog;
use RuntimeException;

/**
 * Saves fine mail metadata into FineProcessLog.
 * SharePoint archiving is owned by IncomingFineDocumentArchiver
 * ({base}/{YYYY}/{Reference}[__{RNT}]/) once driver/reference are known.
 * Local attachment files come from IncomingFineAttachmentTextExtractor.
 */
final class IncomingFineProcessor
{
    public function __construct(
        private readonly MicrosoftGraphInbox $inbox = new MicrosoftGraphInbox,
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

        $log->fill([
            'mailbox' => $mailbox,
            'from_email' => $fromEmail !== '' ? $fromEmail : $log->from_email,
            'subject' => $subject !== '' ? $subject : $log->subject,
            'received_at' => filled($message['received_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse((string) $message['received_at'])
                : $log->received_at,
            'authority_id' => $message['authority_id'] ?? $log->authority_id,
            'authority_name' => $authorityName !== '' ? $authorityName : $log->authority_name,
            'status' => 'processed',
            'attachments_uploaded' => $uploaded,
            'sharepoint_urls' => $sharePointUrls !== [] ? $sharePointUrls : null,
            'error' => null,
            'processed_by' => $userId ?? $log->processed_by,
            'processed_at' => $log->processed_at ?? now(),
        ])->save();

        return [
            'log' => $log,
            'attachments_uploaded' => $uploaded,
            'sharepoint_urls' => $sharePointUrls,
            'moved' => false,
            'error' => null,
        ];
    }
}
