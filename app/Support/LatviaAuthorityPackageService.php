<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Latvian CPP physical-letter package (instruction VI):
 * signed notice + original fine + power of attorney + RNT → kanceleja.cpp@vp.gov.lv
 * Reminder letters: lookup primary offence via E-CSDD.
 */
final class LatviaAuthorityPackageService
{
    public const PURPOSE_LETTER = 'latvia_letter';

    public const PURPOSE_POA = 'power_of_attorney';

    public const ECSDD_URL = 'https://e.csdd.lv/speedcam';

    public const TO_EMAIL = 'kanceleja.cpp@vp.gov.lv';

    public function __construct(
        private readonly AuthorityInfoPdfGenerator $infoPdf = new AuthorityInfoPdfGenerator,
        private readonly PowerOfAttorneyPdfGenerator $poaPdf = new PowerOfAttorneyPdfGenerator,
        private readonly MarkSignService $markSign = new MarkSignService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{
     *     is_reminder: bool,
     *     ecsdd_url: string,
     *     case_no: string,
     *     vehicle: string,
     *     checklist: list<string>,
     *     poa: array{valid:bool,valid_until:?string,signed_path:?string,signing_url:?string}
     * }
     */
    public function assist(array $draft, ?FineProcessLog $log = null): array
    {
        $haystack = strtolower(implode("\n", array_filter([
            (string) ($draft['subject'] ?? ''),
            (string) ($draft['body_text'] ?? ''),
            (string) ($log?->subject ?? ''),
        ])));
        $isReminder = str_contains($haystack, 'priminim')
            || str_contains($haystack, 'reminder')
            || str_contains($haystack, 'atgādināj')
            || str_contains($haystack, 'atgadinaj');

        $caseNo = trim((string) ($draft['reference'] ?? $log?->reference ?? ''));
        $vehicle = strtoupper(trim((string) (
            $draft['wheelsys']['vehicle']
            ?? $draft['vehicle_hint']
            ?? $log?->vehicle
            ?? ''
        )));

        $settings = EmailSetting::current();
        $validUntil = $settings->power_of_attorney_valid_until
            ? \Illuminate\Support\Carbon::parse($settings->power_of_attorney_valid_until)->toDateString()
            : null;
        $poaValid = filled($settings->power_of_attorney_signed_path)
            && $validUntil !== null
            && $validUntil >= now()->toDateString();

        return [
            'is_reminder' => $isReminder,
            'ecsdd_url' => self::ECSDD_URL,
            'case_no' => $caseNo,
            'vehicle' => $vehicle,
            'checklist' => [
                $isReminder
                    ? 'PRIMINIMAS: E-CSDD suvesti bylos nr. + valst. nr., rasti pirminį pažeidimą'
                    : 'Įprastas LV laiškas — naudoti dokumente nurodytą datą / nr.',
                'Sugeneruoti LV pranešimą → MarkSign (Mykolas)',
                'Turėti galiojantį metinį įgaliojimą (Settings → Renew POA)',
                'Siųsti paketą į kanceleja.cpp@vp.gov.lv: pasirašytas raštas + originalas + įgaliojimas + RNT',
                'Po siuntimo informuoti LV/EE padalinį (jei jų klientas)',
            ],
            'poa' => [
                'valid' => $poaValid,
                'valid_until' => $validUntil,
                'signed_path' => $settings->power_of_attorney_signed_path,
                'signing_url' => $settings->power_of_attorney_signing_url,
            ],
        ];
    }

    /**
     * Generate Latvia notice PDF and send to MarkSign for director signature.
     *
     * @param  array<string, mixed>  $draft
     * @return array{log:FineProcessLog,uuid:string,signing_url:string,pdf_path:string}
     */
    public function prepareLetterForMarksign(FineProcessLog $log, array $draft): array
    {
        if (! $this->markSign->isConfigured()) {
            throw new RuntimeException('MarkSign token is not configured.');
        }

        $signer = EmailSetting::marksignSigner();
        if ($signer['email'] === '') {
            throw new RuntimeException('MarkSign signer email is empty.');
        }

        $infoPdf = $this->infoPdf->generateIfRequired($draft, $log);
        if ($infoPdf === null || ($infoPdf['template'] ?? '') !== AuthorityInfoPdfGenerator::TEMPLATE_LATVIA) {
            throw new RuntimeException('Authority is not configured for Latvia info PDF template.');
        }

        $uuid = $this->markSign->uploadDocument($infoPdf['relative_path']);
        $signingUrl = $this->markSign->generateSigningLink($uuid, $signer);

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['info_pdf'] = [
            'file_name' => $infoPdf['file_name'],
            'path' => $infoPdf['relative_path'],
            'document_number' => $infoPdf['document_number'],
            'template' => $infoPdf['template'],
            'sharepoint_url' => $infoPdf['sharepoint_url'] ?? null,
        ];
        $workflow['latvia_package'] = array_merge(
            is_array($workflow['latvia_package'] ?? null) ? $workflow['latvia_package'] : [],
            [
                'letter_status' => 'awaiting_signature',
                'letter_pdf' => $infoPdf['relative_path'],
                'prepared_at' => now()->toIso8601String(),
                'prepared_by' => Auth::id(),
            ]
        );

        $log->fill([
            'marksign_uuid' => $uuid,
            'marksign_purpose' => self::PURPOSE_LETTER,
            'marksign_signing_url' => $signingUrl,
            'marksign_signed_pdf_path' => null,
            'workflow' => $workflow,
        ])->save();

        return [
            'log' => $log->fresh(),
            'uuid' => $uuid,
            'signing_url' => $signingUrl,
            'pdf_path' => $infoPdf['relative_path'],
        ];
    }

    /**
     * Generate / renew annual POA and send to MarkSign.
     *
     * @return array{uuid:string,signing_url:string,valid_until:string,pdf_path:string}
     */
    public function renewPowerOfAttorneyForMarksign(): array
    {
        if (! $this->markSign->isConfigured()) {
            throw new RuntimeException('MarkSign token is not configured.');
        }

        $signer = EmailSetting::marksignSigner();
        if ($signer['email'] === '') {
            throw new RuntimeException('MarkSign signer email is empty.');
        }

        $pdf = $this->poaPdf->generate();
        $uuid = $this->markSign->uploadDocument($pdf['relative_path']);
        $signingUrl = $this->markSign->generateSigningLink($uuid, $signer);

        EmailSetting::current()->fill([
            'power_of_attorney_pdf_path' => $pdf['relative_path'],
            'power_of_attorney_signed_path' => null,
            'power_of_attorney_valid_until' => $pdf['valid_until'],
            'power_of_attorney_marksign_uuid' => $uuid,
            'power_of_attorney_signing_url' => $signingUrl,
        ])->save();

        return [
            'uuid' => $uuid,
            'signing_url' => $signingUrl,
            'valid_until' => $pdf['valid_until'],
            'pdf_path' => $pdf['relative_path'],
        ];
    }

    public function markPowerOfAttorneySigned(string $signedRelativePath): void
    {
        EmailSetting::current()->fill([
            'power_of_attorney_signed_path' => $signedRelativePath,
            'power_of_attorney_signing_url' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  string|null  $rntRelativePath  Optional uploaded RNT PDF (local disk relative)
     * @return array{to:string,attachments:int}
     */
    public function sendPackage(FineProcessLog $log, array $draft, ?string $rntRelativePath = null): array
    {
        $settings = EmailSetting::current();
        $signedLetter = trim((string) ($log->marksign_signed_pdf_path ?? ''));
        if ($signedLetter === '' || ! Storage::disk('local')->exists($signedLetter)) {
            throw new RuntimeException('Pasirašyto LV pranešimo dar nėra. Palaukite MarkSign parašo.');
        }

        $poa = trim((string) ($settings->power_of_attorney_signed_path ?? ''));
        if ($poa === '' || ! Storage::disk('local')->exists($poa)) {
            throw new RuntimeException('Nėra galiojančio pasirašyto įgaliojimo. Settings → Renew power of attorney.');
        }
        if ($settings->power_of_attorney_valid_until
            && \Illuminate\Support\Carbon::parse($settings->power_of_attorney_valid_until)->lt(now()->startOfDay())
        ) {
            throw new RuntimeException('Įgaliojimo galiojimas pasibaigęs. Atnaujinkite POA Settings.');
        }

        $from = trim((string) ($draft['from_email'] ?? EmailSetting::graphConfig()['from_email'] ?? ''));
        if ($from === '') {
            throw new RuntimeException('From email is empty.');
        }

        $caseNo = trim((string) ($draft['reference'] ?? $log->reference ?? ''));
        $subject = $caseNo !== '' ? $caseNo : 'Traffic offence notice';
        $html = '<p>Please find attached:</p><ul>'
            .'<li>signed notice regarding the offender</li>'
            .'<li>original fine / physical letter</li>'
            .'<li>power of attorney</li>'
            .($rntRelativePath ? '<li>rental agreement (RNT)</li>' : '')
            .'</ul>';

        $attachments = [
            [
                'path' => $signedLetter,
                'name' => 'signed-notice-'.basename($signedLetter),
                'content_type' => 'application/pdf',
            ],
            [
                'path' => $poa,
                'name' => 'power-of-attorney.pdf',
                'content_type' => 'application/pdf',
            ],
        ];

        foreach ($this->originalNoticeAttachments($log) as $notice) {
            $attachments[] = $notice;
        }

        if ($rntRelativePath && Storage::disk('local')->exists($rntRelativePath)) {
            $attachments[] = [
                'path' => $rntRelativePath,
                'name' => 'RNT-'.basename($rntRelativePath),
                'content_type' => 'application/pdf',
            ];
        }

        app(MicrosoftGraphMailer::class)->sendHtml(
            fromEmail: $from,
            toEmail: self::TO_EMAIL,
            subject: $subject,
            htmlBody: $html,
            replyTo: EmailSetting::graphConfig()['reply_to'] ?: null,
            attachments: $attachments,
        );

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['latvia_package'] = array_merge(
            is_array($workflow['latvia_package'] ?? null) ? $workflow['latvia_package'] : [],
            [
                'letter_status' => 'sent',
                'sent_at' => now()->toIso8601String(),
                'sent_to' => self::TO_EMAIL,
                'attachment_count' => count($attachments),
                'rnt_attached' => (bool) $rntRelativePath,
            ]
        );

        $log->fill([
            'email_to' => self::TO_EMAIL,
            'email_sent_at' => now(),
            'status' => 'sent',
            'workflow' => $workflow,
            'processed_at' => $log->processed_at ?? now(),
            'processed_by' => $log->processed_by ?? Auth::id(),
        ])->save();

        return [
            'to' => self::TO_EMAIL,
            'attachments' => count($attachments),
        ];
    }

    /**
     * @return list<array{path:string,name:string,content_type:string}>
     */
    private function originalNoticeAttachments(FineProcessLog $log): array
    {
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
            if (! str_ends_with($base, '.pdf') && ! preg_match('/\.(jpe?g|png|webp)$/', $base)) {
                continue;
            }
            if (str_contains($base, 'combined') || str_ends_with($base, '.extract.txt')) {
                continue;
            }

            $out[] = [
                'path' => $relative,
                'name' => basename($relative),
                'content_type' => str_ends_with($base, '.pdf') ? 'application/pdf' : 'application/octet-stream',
            ];
        }

        return $out;
    }
}
