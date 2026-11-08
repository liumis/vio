<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class IncomingFineReplySender
{
    /**
     * @param  array<string, mixed>  $draft
     */
    public function send(array $draft, ?FineProcessLog $log = null): FineProcessLog
    {
        $to = trim((string) ($draft['to'] ?? ''));
        $from = trim((string) ($draft['from_email'] ?? ''));
        $subject = trim((string) ($draft['subject'] ?? ''));
        $bodyText = (string) ($draft['body_text'] ?? '');

        if ($from === '' || $subject === '') {
            throw new RuntimeException('From and Subject are required to send.');
        }

        $builder = app(IncomingFineReplyBuilder::class);
        if ($to === '' || $builder->isNoReplyEmail($to)) {
            throw new RuntimeException(
                'Cannot send: recipient is empty or a no-reply address. '
                .'ANR/ROIK replies go via IRD ePaslaugos (Transporto priemonės naudotojo teikimas), not email.'
            );
        }

        $html = $builder->renderNeutralHtml($subject, $bodyText);
        $replyTo = trim((string) ($draft['reply_to'] ?? ''));

        $attachments = [];
        $infoPdf = app(AuthorityInfoPdfGenerator::class)->generateIfRequired($draft, $log);
        if ($infoPdf !== null) {
            $attachments[] = [
                'path' => $infoPdf['relative_path'],
                'name' => $infoPdf['file_name'],
                'content_type' => 'application/pdf',
            ];
        }

        foreach ($this->originalNoticeAttachments($log) as $notice) {
            $attachments[] = $notice;
        }

        app(MicrosoftGraphMailer::class)->sendHtml(
            fromEmail: $from,
            toEmail: $to,
            subject: $subject,
            htmlBody: $html,
            replyTo: $replyTo !== '' ? $replyTo : null,
            attachments: $attachments,
        );

        if ($log === null) {
            $log = new FineProcessLog([
                'graph_message_id' => 'manual-send-'.uniqid('', true),
                'subject' => $subject,
                'authority_name' => $draft['authority_name'] ?? null,
                'authority_id' => $draft['authority_id'] ?? null,
            ]);
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        if ($infoPdf !== null) {
            $workflow['info_pdf'] = [
                'file_name' => $infoPdf['file_name'],
                'path' => $infoPdf['relative_path'],
                'document_number' => $infoPdf['document_number'],
                'template' => $infoPdf['template'] ?? null,
                'sharepoint_url' => $infoPdf['sharepoint_url'] ?? null,
            ];
        }

        $log->fill([
            'email_to' => $to,
            'email_sent_at' => now(),
            'status' => 'sent',
            'workflow' => $workflow,
            'processed_at' => $log->processed_at ?? now(),
            'processed_by' => $log->processed_by ?? auth()->id(),
        ])->save();

        return $log;
    }

    /**
     * Attach the received fine notice PDF(s) from local archive (instruction: gautą pranešimo dokumentą).
     *
     * @return list<array{path:string,name:string,content_type:string}>
     */
    private function originalNoticeAttachments(?FineProcessLog $log): array
    {
        if ($log === null) {
            return [];
        }

        $dir = trim(str_replace('\\', '/', (string) ($log->local_attachment_dir ?? '')), '/');
        if ($dir === '') {
            return [];
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($dir)) {
            return [];
        }

        $out = [];
        foreach ($disk->files($dir) as $relative) {
            $base = strtolower(basename($relative));
            if (! str_ends_with($base, '.pdf')) {
                continue;
            }
            if (str_contains($base, 'combined') || str_ends_with($base, '.extract.txt')) {
                continue;
            }

            $out[] = [
                'path' => $relative,
                'name' => basename($relative),
                'content_type' => 'application/pdf',
            ];
        }

        return $out;
    }
}
