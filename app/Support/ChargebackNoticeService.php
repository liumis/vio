<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * IV Veiksmai po nepavykusio nuskaičiavimo:
 * bilingual notice PDF → MarkSign → Mykolas signs.
 */
final class ChargebackNoticeService
{
    public function __construct(
        private readonly MarkSignService $markSign = new MarkSignService,
    ) {
    }

    /**
     * @param  array{
     *     client_name: string,
     *     client_email: string,
     *     rental_no: string,
     *     reference: string,
     *     fine_amount: float|int|string,
     *     admin_fee: float|int|string,
     *     admin_fee_letter_date?: string|null,
     *     company_paid_fine?: bool
     * }  $data
     * @return array{log: FineProcessLog, signing_url: string, marksign_uuid: string, pdf_path: string}
     */
    public function markFailedAndSendToMarksign(FineProcessLog $log, array $data): array
    {
        if (! $this->markSign->isConfigured()) {
            throw new RuntimeException('MarkSign token is not configured. Add it in System settings or MARKSIGN_TOKEN.');
        }

        $signer = EmailSetting::marksignSigner();
        if ($signer['email'] === '') {
            throw new RuntimeException('MarkSign signer email is empty. Set it in System settings (e.g. mykolas@sitandgo.lt).');
        }

        $clientName = trim((string) ($data['client_name'] ?? ''));
        $clientEmail = trim((string) ($data['client_email'] ?? ''));
        $rentalNo = trim((string) ($data['rental_no'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? $log->reference ?? ''));
        $fineAmount = round((float) ($data['fine_amount'] ?? 0), 2);
        $adminFee = round((float) ($data['admin_fee'] ?? 72.60), 2);
        $adminFeeLetterDate = trim((string) ($data['admin_fee_letter_date'] ?? '')) ?: null;
        $companyPaidFine = (bool) ($data['company_paid_fine'] ?? ($fineAmount > 0));
        $invNumber = trim((string) ($data['inv_number'] ?? ''));

        if ($clientName === '' || $clientEmail === '' || $reference === '') {
            throw new RuntimeException('Client name, email and reference are required.');
        }
        if ($fineAmount <= 0 && $adminFee <= 0) {
            throw new RuntimeException('Enter fine amount and/or administrative fee.');
        }

        $total = round($fineAmount + $adminFee, 2);
        $pdfRelative = $this->generatePdf([
            'clientName' => $clientName,
            'clientEmail' => $clientEmail,
            'rentalNo' => $rentalNo !== '' ? $rentalNo : '—',
            'reference' => $reference,
            'fineAmount' => $fineAmount,
            'adminFee' => $adminFee,
            'totalAmount' => $total,
            'adminFeeLetterDate' => $adminFeeLetterDate,
            'companyPaidFine' => $companyPaidFine,
            'signerFullName' => trim($signer['name'].' '.$signer['surname']),
        ], $reference);

        $uuid = $this->markSign->uploadDocument($pdfRelative);
        $signingUrl = $this->markSign->generateSigningLink($uuid, $signer);

        $payload = [
            'client_name' => $clientName,
            'client_email' => $clientEmail,
            'rental_no' => $rentalNo,
            'reference' => $reference,
            'inv_number' => $invNumber !== '' ? $invNumber : null,
            'fine_amount' => $fineAmount,
            'admin_fee' => $adminFee,
            'total_amount' => $total,
            'admin_fee_letter_date' => $adminFeeLetterDate,
            'company_paid_fine' => $companyPaidFine,
        ];

        $log->fill([
            'chargeback_status' => FineProcessLog::CHARGEBACK_AWAITING_SIGNATURE,
            'chargeback_data' => $payload,
            'chargeback_pdf_path' => $pdfRelative,
            'marksign_uuid' => $uuid,
            'marksign_purpose' => 'chargeback',
            'marksign_signing_url' => $signingUrl,
            'chargeback_marked_at' => now(),
            'chargeback_marked_by' => Auth::id(),
            'driver_name' => $log->driver_name ?: $clientName,
            'reference' => $log->reference ?: $reference,
            'agr_no' => $log->agr_no ?: ($rentalNo !== '' ? $rentalNo : null),
        ])->save();

        return [
            'log' => $log->fresh(),
            'signing_url' => $signingUrl,
            'marksign_uuid' => $uuid,
            'pdf_path' => $pdfRelative,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generatePdf(array $payload, string $reference): string
    {
        $htmlPayload = array_merge([
            'companyName' => 'UAB BMS Technologija',
            'companyCode' => '300138495',
            'companyAddress' => 'A. Juozapavičiaus 10A-4, Vilnius, LT-09311, Lietuvos Respublika',
            'companyPhone' => '+370 600 23699',
            'companyEmail' => 'info@greenmotion.lt',
            'documentDate' => now()->timezone(config('app.timezone'))->format('Y-m-d'),
        ], $payload);

        $binary = Pdf::loadView('pdfs.chargeback-notice', $htmlPayload)->setPaper('a4')->output();
        if ($binary === '' || $binary === false) {
            throw new RuntimeException('Failed to generate chargeback notice PDF.');
        }

        $safeRef = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $reference));
        $safeRef = trim($safeRef, " .-_") ?: 'chargeback';
        $relative = 'chargeback-notices/'.now()->format('Ymd').'/'.Str::limit($safeRef, 80, '').'-notice.pdf';
        Storage::disk('local')->put($relative, $binary);

        return $relative;
    }
}
