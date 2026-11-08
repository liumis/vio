<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Archives the fine PDF to SharePoint using instruction naming:
 * "{Driver Name} {Reference}.pdf"
 */
final class IncomingFineDocumentArchiver
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
        $existing = $this->primaryUrl($log);
        if ($existing !== null && ! $forceReupload) {
            return $existing;
        }

        $driver = trim((string) ($log->driver_name ?? ''));
        $reference = trim((string) ($log->reference ?? ''));
        if ($driver === '' || $reference === '') {
            return $existing;
        }

        $localPath = $this->resolveLocalPdfPath($log);
        if ($localPath === null) {
            return $existing;
        }

        $contents = @file_get_contents($localPath);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Could not read fine PDF: '.$localPath);
        }

        $fileName = $this->documentFileName($driver, $reference, pathinfo($localPath, PATHINFO_EXTENSION) ?: 'pdf');

        try {
            $upload = $this->sharePoint->upload($fileName, $contents);
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

        $log->fill([
            'sharepoint_urls' => $urls,
            'attachments_uploaded' => max((int) $log->attachments_uploaded, count($urls)),
        ])->save();

        return $url;
    }

    public function primaryUrl(FineProcessLog $log): ?string
    {
        $urls = is_array($log->sharepoint_urls) ? $log->sharepoint_urls : [];
        foreach ($urls as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    public function documentFileName(string $driverName, string $reference, string $extension = 'pdf'): string
    {
        $base = trim(preg_replace('/\s+/', ' ', $driverName.' '.$reference) ?? '');
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
