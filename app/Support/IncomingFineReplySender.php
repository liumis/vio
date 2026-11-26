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

        $rntAttached = [];
        if ($log !== null && $this->draftRequiresContract($draft, $log)) {
            foreach ($this->rentalContractAttachments($log) as $rnt) {
                $attachments[] = $rnt;
                $rntAttached[] = $rnt['name'];
            }
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
        if ($rntAttached !== []) {
            $workflow['rnt_attached_on_send'] = [
                'files' => $rntAttached,
                'agr_no' => $log->agr_no,
                'rental_id' => $log->rental_id,
                'at' => now()->toIso8601String(),
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

        // Authority reply sent → move original notice to "VIO processed".
        try {
            app(IncomingFineMailboxFolders::class)->moveToProcessed($log->fresh());
        } catch (\Throwable) {
            // Non-fatal — mail already sent.
        }

        return $log;
    }

    /**
     * Attach the received fine notice PDF(s) from local archive (instruction: gautą pranešimo dokumentą).
     * Falls back to SharePoint primary fine document when local PDF folder is empty.
     *
     * @return list<array{path:string,name:string,content_type:string}>
     */
    private function originalNoticeAttachments(?FineProcessLog $log): array
    {
        if ($log === null) {
            return [];
        }

        $disk = Storage::disk('local');
        $out = [];

        $dir = trim(str_replace('\\', '/', (string) ($log->local_attachment_dir ?? '')), '/');
        if ($dir !== '' && $disk->exists($dir)) {
            foreach ($disk->files($dir) as $relative) {
                $base = strtolower(basename($relative));
                if (! str_ends_with($base, '.pdf')) {
                    continue;
                }
                if (str_contains($base, 'combined') || str_ends_with($base, '.extract.txt')) {
                    continue;
                }
                if (str_starts_with($base, 'rnt-')) {
                    continue;
                }

                $out[] = [
                    'path' => $relative,
                    'name' => basename($relative),
                    'content_type' => 'application/pdf',
                ];
            }
        }

        if ($out !== []) {
            return $out;
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $storedDoc = is_array($workflow['sharepoint_document'] ?? null) ? $workflow['sharepoint_document'] : [];
        $fileName = trim((string) ($storedDoc['file'] ?? ''));
        $folder = trim((string) ($storedDoc['folder'] ?? ''));
        if ($fileName === '') {
            $url = $log->documentSharePointUrl();
            if ($url === null || $url === '') {
                return [];
            }
            $fileName = basename(urldecode((string) (parse_url($url, PHP_URL_PATH) ?: '')));
            if ($folder === '') {
                $folder = app(IncomingFineDocumentArchiver::class)->documentFolderPathForLog($log);
            }
        }
        if ($fileName === '' || str_contains($fileName, '/') || str_starts_with(strtolower($fileName), 'rnt-')) {
            return [];
        }

        try {
            $binary = app(MicrosoftGraphSharePoint::class)->download(
                $fileName,
                $folder !== '' ? $folder : null,
            );
            if ($binary === '' || ! str_starts_with($binary, '%PDF')) {
                return [];
            }
            $safeName = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $fileName) ?: 'fine-notice.pdf';
            $relative = 'private/notice-send/'.$log->id.'-'.uniqid('', true).'/'.$safeName;
            $disk->put($relative, $binary);
            if (! $disk->exists($relative) || $disk->size($relative) < 1) {
                return [];
            }

            return [[
                'path' => $relative,
                'name' => $safeName,
                'content_type' => 'application/pdf',
            ]];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function draftRequiresContract(array $draft, FineProcessLog $log): bool
    {
        if (! empty($draft['requires_contract'])) {
            return true;
        }

        $authority = $log->relationLoaded('authority')
            ? $log->authority
            : ($log->authority_id ? \App\Models\Authority::query()->find($log->authority_id) : null);

        return $authority?->requiresContract() === true;
    }

    /**
     * Julianus: attach rental contract PDFs from Wheelsys (and archive to SharePoint).
     *
     * @return list<array{path:string,name:string,content_type:string}>
     */
    private function rentalContractAttachments(FineProcessLog $log): array
    {
        if (! filled($log->rental_id)) {
            throw new RuntimeException(
                'Julianus requires the rental contract (RNT). Pick a Wheelsys rental before sending.'
            );
        }

        // One Wheelsys download — attach to mail; SharePoint archive is best-effort (same bytes).
        $files = app(WheelsysClient::class)->downloadRentalAttachmentFiles((int) $log->rental_id);
        if ($files === []) {
            throw new RuntimeException(
                'Julianus requires the rental contract (RNT), but Wheelsys rental has no attached documents.'
            );
        }

        try {
            app(WheelsysRentalDocumentArchiver::class)->archiveDownloadedFiles($log, $files);
        } catch (\Throwable) {
            // Mail attach still proceeds.
        }

        $disk = Storage::disk('local');
        $dir = 'private/rnt-send/'.$log->id.'-'.uniqid('', true);
        $disk->makeDirectory($dir);

        $out = [];
        $agrNo = trim((string) ($log->agr_no ?? ''));
        $reference = trim((string) ($log->reference ?? ''));
        foreach ($files as $file) {
            $rawName = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'rnt.pdf')));
            $rawName = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $rawName) ?: 'rnt.pdf';
            $lower = strtolower($rawName);
            // Contracts only — skip photos / screenshots from the rental Dropzone.
            if (! str_ends_with($lower, '.pdf')) {
                continue;
            }
            // Skip the fine notice copy that was uploaded into Wheelsys rental attachments.
            if ($reference !== '' && str_contains($lower, strtolower($reference))) {
                continue;
            }

            if ($agrNo !== '' && ! str_contains($rawName, $agrNo)) {
                $ext = pathinfo($rawName, PATHINFO_EXTENSION);
                $stem = pathinfo($rawName, PATHINFO_FILENAME);
                $rawName = $stem.'-'.$agrNo.($ext !== '' ? '.'.$ext : '');
            }
            if (! str_starts_with(strtolower($rawName), 'rnt-')) {
                $rawName = 'rnt-'.$rawName;
            }

            $relative = $dir.'/'.$rawName;
            $disk->put($relative, (string) ($file['contents'] ?? ''));
            if (! $disk->exists($relative) || $disk->size($relative) < 1) {
                continue;
            }

            $out[] = [
                'path' => $relative,
                'name' => $rawName,
                'content_type' => 'application/pdf',
            ];
        }

        if ($out === []) {
            throw new RuntimeException(
                'Could not prepare rental contract PDF attachments for Julianus reply '
                .'(Wheelsys rental had no usable contract PDF).'
            );
        }

        return $out;
    }

    /**
     * Dry-run: resolve notice + RNT attachments that would be sent (no email).
     *
     * @param  array<string, mixed>  $draft
     * @return array{
     *     notice: list<array{name:string,bytes:int}>,
     *     contracts: list<array{name:string,bytes:int}>,
     *     requires_contract: bool
     * }
     */
    public function previewAttachments(array $draft, FineProcessLog $log): array
    {
        $notice = [];
        foreach ($this->originalNoticeAttachments($log) as $file) {
            $notice[] = [
                'name' => $file['name'],
                'bytes' => (int) Storage::disk('local')->size($file['path']),
            ];
        }

        $contracts = [];
        $requires = $this->draftRequiresContract($draft, $log);
        if ($requires) {
            foreach ($this->rentalContractAttachments($log) as $file) {
                $contracts[] = [
                    'name' => $file['name'],
                    'bytes' => (int) Storage::disk('local')->size($file['path']),
                ];
            }
        }

        return [
            'notice' => $notice,
            'contracts' => $contracts,
            'requires_contract' => $requires,
        ];
    }
}
