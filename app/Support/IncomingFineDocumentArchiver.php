<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Archives the fine PDF to SharePoint using instruction naming and a browsable folder tree:
 *
 *   {base}/{YYYY}/{Reference}[__{RNT}]/{Driver} {Reference}.pdf
 *
 * Example:
 *   Lithuania/Administration/Policijos administravimas/2026/26316319149__RNT-76970/Jonas Jonaitis 26316319149.pdf
 */
class IncomingFineDocumentArchiver
{
    public function __construct(
        private readonly MicrosoftGraphSharePoint $sharePoint = new MicrosoftGraphSharePoint,
    ) {
    }

    /**
     * Upload (or keep) the primary fine document for a log. Returns the SharePoint web URL.
     */
    public function archive(FineProcessLog $log, bool $forceReupload = false): ?string
    {
        $folder = $this->documentFolderPathForLog($log);
        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $stored = is_array($workflow['sharepoint_document'] ?? null) ? $workflow['sharepoint_document'] : [];
        $existing = $this->primaryUrl($log);

        if (
            ! $forceReupload
            && $existing !== null
            && trim((string) ($stored['folder'] ?? '')) === $folder
            && filled($stored['url'] ?? null)
        ) {
            return $existing;
        }

        $reference = trim((string) ($log->reference ?? ''));
        if ($reference === '' && trim((string) ($log->vehicle ?? '')) === '') {
            return $existing;
        }

        $driver = trim((string) ($log->driver_name ?? ''));
        if ($driver === '') {
            $driver = 'Unknown driver';
        }

        $localPath = $this->resolveLocalPdfPath($log);
        if ($localPath === null) {
            return $existing;
        }

        $contents = @file_get_contents($localPath);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Could not read fine PDF: '.$localPath);
        }

        $fileName = $this->documentFileName(
            $driver,
            $reference !== '' ? $reference : (string) $log->vehicle,
            pathinfo($localPath, PATHINFO_EXTENSION) ?: 'pdf',
            $log->agr_no
        );

        try {
            $this->sharePoint->ensureFolderExists($folder);
            $upload = $this->sharePoint->upload($fileName, $contents, $folder);
        } catch (Throwable $e) {
            throw new RuntimeException('SharePoint fine-document upload failed: '.$e->getMessage(), 0, $e);
        }

        $url = trim((string) ($upload['webUrl'] ?? ''));
        if ($url === '') {
            return $existing;
        }

        $urls = is_array($log->sharepoint_urls) ? array_values($log->sharepoint_urls) : [];
        array_unshift($urls, $url);
        $urls = array_values(array_unique(array_filter($urls, static fn ($u): bool => is_string($u) && trim($u) !== '')));

        $workflow['sharepoint_document'] = [
            'folder' => $folder,
            'file' => $fileName,
            'url' => $url,
            'archived_at' => now()->toIso8601String(),
        ];

        $log->fill([
            'sharepoint_urls' => $urls,
            'attachments_uploaded' => max((int) $log->attachments_uploaded, count($urls)),
            'workflow' => $workflow,
        ])->save();

        return $url;
    }

    public function primaryUrl(FineProcessLog $log): ?string
    {
        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $storedUrl = trim((string) (($workflow['sharepoint_document']['url'] ?? '') ?: ''));
        if ($storedUrl !== '') {
            return $storedUrl;
        }

        $urls = is_array($log->sharepoint_urls) ? $log->sharepoint_urls : [];
        foreach ($urls as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    public function documentFolderPathForLog(FineProcessLog $log): string
    {
        return $this->documentFolderPath(
            reference: $log->reference,
            date: $log->ticket_date ?? $log->received_at ?? now(),
            agrNo: $log->agr_no,
            vehicle: $log->vehicle,
        );
    }

    /**
     * Browsable path under the configured SharePoint base folder.
     *
     * Prefer: {base}/{YYYY}/{Reference}[__{RNT}]
     * Fallback without reference: {base}/{YYYY}/_unsorted/{Vehicle|unknown}
     */
    public function documentFolderPath(
        ?string $reference = null,
        CarbonInterface|\DateTimeInterface|string|null $date = null,
        ?string $agrNo = null,
        ?string $vehicle = null,
    ): string {
        $base = $this->baseFolder();
        $year = $this->resolveYear($date);

        $reference = $this->safeSegment((string) ($reference ?? ''), 80);
        $agrNo = $this->safeSegment((string) ($agrNo ?? ''), 40);
        $vehicle = $this->safeSegment(strtoupper(trim((string) ($vehicle ?? ''))), 20);

        if ($reference !== '') {
            $leaf = $agrNo !== '' ? $reference.'__'.$agrNo : $reference;

            return $base.'/'.$year.'/'.$leaf;
        }

        $unsorted = $vehicle !== '' ? $vehicle : 'unknown';

        return $base.'/'.$year.'/_unsorted/'.$unsorted;
    }

    public function documentFileName(
        string $driverName,
        string $reference,
        string $extension = 'pdf',
        ?string $agrNo = null,
    ): string {
        $parts = [trim($driverName), trim($reference)];
        $agrNo = trim((string) $agrNo);
        if ($agrNo !== '') {
            $parts[] = $agrNo;
        }

        $base = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))) ?? '');
        $base = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $base);
        $base = trim($base, " .-_");
        if ($base === '') {
            $base = 'fine-document';
        }

        $ext = strtolower(ltrim($extension, '.'));
        if ($ext === '' || ! preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
            $ext = 'pdf';
        }

        return Str::limit($base, 160, '').'.'.$ext;
    }

    public function baseFolder(): string
    {
        return trim((string) (EmailSetting::sharePointConfig()['folder_path'] ?? ''), '/');
    }

    private function resolveYear(CarbonInterface|\DateTimeInterface|string|null $date): string
    {
        if ($date instanceof CarbonInterface || $date instanceof \DateTimeInterface) {
            return $date->format('Y');
        }

        $raw = trim((string) ($date ?? ''));
        if ($raw !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($raw)->format('Y');
            } catch (Throwable) {
                // fall through
            }
        }

        return now()->timezone(config('app.timezone'))->format('Y');
    }

    private function safeSegment(string $value, int $maxLen): string
    {
        $value = trim(str_replace(['\\', '/'], '-', $value));
        $value = str_replace([':', '*', '?', '"', '<', '>', '|', '#'], '-', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = trim($value, " .-_");
        if ($value === '') {
            return '';
        }

        return Str::limit($value, $maxLen, '');
    }

    private function resolveLocalPdfPath(FineProcessLog $log): ?string
    {
        $dir = trim(str_replace('\\', '/', (string) ($log->local_attachment_dir ?? '')), '/');
        if ($dir === '') {
            return null;
        }

        $candidates = [
            Storage::disk('local')->path($dir),
            storage_path('app/private/'.$dir),
            storage_path('app/'.$dir),
            $dir,
        ];

        $absolute = null;
        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $absolute = $candidate;
                break;
            }
        }
        if ($absolute === null) {
            return null;
        }

        $pdfs = glob($absolute.DIRECTORY_SEPARATOR.'*.pdf') ?: [];
        $pdfs = array_values(array_filter($pdfs, static fn (string $path): bool => is_file($path)));
        if ($pdfs === []) {
            return null;
        }

        // Prefer email-print.pdf (JUDU/Unipark/Parkavimas), then other PDFs, skip "combined".
        usort($pdfs, static function (string $a, string $b): int {
            $aBase = strtolower(basename($a));
            $bBase = strtolower(basename($b));
            $score = static function (string $base): int {
                if ($base === IncomingFineEmailPrintPdf::LOCAL_FILE) {
                    return 0;
                }
                if (str_contains($base, 'combined')) {
                    return 2;
                }

                return 1;
            };

            return $score($aBase) <=> $score($bBase) ?: strcmp($aBase, $bBase);
        });

        return $pdfs[0];
    }
}
