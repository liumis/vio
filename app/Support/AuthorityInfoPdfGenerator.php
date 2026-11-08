<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\FineProcessLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Formal "informacijos pateikimo" PDF for authorities that require a letter (instruction).
 * Filename = registration / reference number (e.g. A51-56775.pdf).
 */
final class AuthorityInfoPdfGenerator
{
    public const TEMPLATE_VILNIUS = 'vilnius';

    public const TEMPLATE_LATVIA = 'latvia';

    /**
     * @param  array<string, mixed>  $draft  Reply draft from IncomingFineReplyBuilder
     * @return array{relative_path: string, absolute_path: string, file_name: string, document_number: string}|null
     */
    public function generateIfRequired(array $draft, ?FineProcessLog $log = null): ?array
    {
        $authority = $this->resolveAuthority($draft, $log);
        if ($authority === null) {
            return null;
        }

        $template = $this->resolveTemplate($authority);
        if ($template === null) {
            return null;
        }

        $reference = trim((string) ($draft['reference'] ?? $log?->reference ?? ''));
        if ($reference === '') {
            throw new RuntimeException('Cannot build info PDF without a registration / reference number.');
        }

        $wheelsys = is_array($draft['wheelsys'] ?? null) ? $draft['wheelsys'] : [];
        $documentNumber = $this->nextDocumentNumber();
        $payload = [
            'companyName' => 'UAB BMS Technologija',
            'companyCode' => '300138495',
            'companyAddress' => 'A. Juozapavičiaus 10A-4, Vilnius, LT-09311, Lietuvos Respublika',
            'companyPhone' => '+370 600 23699',
            'companyEmail' => 'administration@greenlease.lt',
            'signerTitle' => 'Direktorius',
            'documentNumber' => $documentNumber,
            'documentDate' => now()->timezone(config('app.timezone'))->format('Y-m-d'),
            'reference' => $reference,
            'driver' => $this->plain((string) ($wheelsys['driver'] ?? $log?->driver_name ?? '')),
            'vehicle' => strtoupper($this->plain((string) ($wheelsys['vehicle'] ?? $log?->vehicle ?? $draft['vehicle_hint'] ?? ''))),
            'ticketDate' => $this->plain((string) ($wheelsys['ticket_date'] ?? $log?->ticket_date?->format('Y-m-d') ?? $draft['ticket_date_hint'] ?? '')),
            'birthDate' => $this->plain((string) ($wheelsys['birth_date'] ?? '')),
            'email' => $this->plain((string) ($wheelsys['customer_email'] ?? '')),
            'address' => $this->plain(trim(implode(', ', array_filter([
                (string) ($wheelsys['driver_address'] ?? ''),
                (string) ($wheelsys['driver_city'] ?? ''),
                (string) ($wheelsys['driver_country'] ?? ''),
            ])))),
            'phone' => $this->plain((string) ($wheelsys['driver_telephone'] ?? '')),
            'license' => $this->plain((string) ($wheelsys['driver_license'] ?? '')),
            'rental' => $this->plain((string) ($wheelsys['agr_no'] ?? $log?->agr_no ?? '')),
        ];

        $view = $template === self::TEMPLATE_LATVIA
            ? 'pdfs.authority-info-latvia'
            : 'pdfs.authority-info-vilnius';

        $pdf = Pdf::loadView($view, $payload)->setPaper('a4');
        $binary = $pdf->output();
        if ($binary === '' || $binary === false) {
            throw new RuntimeException('Dompdf returned an empty PDF.');
        }

        $fileName = $this->safeFileName($reference).'.pdf';
        $relative = 'authority-info-pdfs/'.now()->format('Ymd').'/'.$fileName;
        Storage::disk('local')->put($relative, $binary);

        $sharePointUrl = null;
        try {
            $upload = app(MicrosoftGraphSharePoint::class)->upload($fileName, $binary);
            $sharePointUrl = trim((string) ($upload['webUrl'] ?? '')) ?: null;
        } catch (\Throwable) {
            // Local PDF + email attachment still succeed if SharePoint is unavailable.
        }

        return [
            'relative_path' => $relative,
            'absolute_path' => Storage::disk('local')->path($relative),
            'file_name' => $fileName,
            'document_number' => $documentNumber,
            'template' => $template,
            'sharepoint_url' => $sharePointUrl,
        ];
    }

    public function resolveTemplate(?Authority $authority): ?string
    {
        if ($authority === null) {
            return null;
        }

        $explicit = strtolower(trim((string) ($authority->info_pdf_template ?? '')));
        if (in_array($explicit, [self::TEMPLATE_VILNIUS, self::TEMPLATE_LATVIA], true)) {
            return $explicit;
        }

        $pattern = strtolower((string) ($authority->email_pattern ?? ''));
        if (str_contains($pattern, 'vilnius.lt')) {
            return self::TEMPLATE_VILNIUS;
        }
        if (str_contains($pattern, 'vp.gov.lv')) {
            return self::TEMPLATE_LATVIA;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function resolveAuthority(array $draft, ?FineProcessLog $log): ?Authority
    {
        $id = (int) ($draft['authority_id'] ?? $log?->authority_id ?? 0);
        if ($id > 0) {
            return Authority::query()->find($id);
        }

        $name = trim((string) ($draft['authority_name'] ?? $log?->authority_name ?? ''));
        if ($name !== '') {
            return Authority::query()->where('name', $name)->first();
        }

        return null;
    }

    private function nextDocumentNumber(): string
    {
        $day = now()->timezone(config('app.timezone'))->format('Y-m-d');
        $dir = 'authority-info-pdfs/'.now()->format('Ymd');
        $existing = Storage::disk('local')->exists($dir)
            ? count(Storage::disk('local')->files($dir))
            : 0;

        return $day.'/'.($existing + 1);
    }

    private function safeFileName(string $reference): string
    {
        $base = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $reference));
        $base = trim(preg_replace('/\s+/', ' ', $base) ?? $base);
        $base = trim($base, " .-_");

        return $base !== '' ? Str::limit($base, 120, '') : 'info-document';
    }

    private function plain(string $value): string
    {
        return trim(str_replace(['**', '__'], '', $value));
    }
}
