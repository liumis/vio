<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pulls Rental "Attached documents" from Wheelsys and stores them next to the fine PDF:
 *   {base}/{YYYY}/{Reference}[__{RNT}]/rnt-{originalName}
 */
final class WheelsysRentalDocumentArchiver
{
    public function __construct(
        private readonly WheelsysClient $wheelsys = new WheelsysClient,
        private readonly MicrosoftGraphSharePoint $sharePoint = new MicrosoftGraphSharePoint,
        private readonly IncomingFineDocumentArchiver $fineArchiver = new IncomingFineDocumentArchiver,
    ) {
    }

    /**
     * @return array{
     *     folder: string,
     *     uploaded: list<array{name:string,url:string,bytes:int}>,
     *     skipped: list<string>,
     *     count: int
     * }
     */
    public function archiveForLog(FineProcessLog $log, bool $force = false): array
    {
        $rentalId = $log->rental_id;
        if (! filled($rentalId)) {
            throw new RuntimeException('Fine has no Wheelsys rental_id — cannot fetch RNT attachments.');
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $stored = is_array($workflow['sharepoint_rnt_documents'] ?? null)
            ? $workflow['sharepoint_rnt_documents']
            : [];
        if (! $force && ! empty($stored['urls']) && is_array($stored['urls'])) {
            return [
                'folder' => (string) ($stored['folder'] ?? $this->fineArchiver->documentFolderPathForLog($log)),
                'uploaded' => array_values(array_filter(array_map(
                    static function ($url): ?array {
                        if (! is_string($url) || trim($url) === '') {
                            return null;
                        }

                        return ['name' => basename(parse_url($url, PHP_URL_PATH) ?: 'file'), 'url' => $url, 'bytes' => 0];
                    },
                    $stored['urls']
                ))),
                'skipped' => ['already archived'],
                'count' => count($stored['urls']),
            ];
        }

        $files = $this->wheelsys->downloadRentalAttachmentFiles((int) $rentalId);
        if ($files === []) {
            throw new RuntimeException('No files inside Wheelsys rental attachments ZIP.');
        }

        return $this->archiveDownloadedFiles($log, $files);
    }

    /**
     * Upload already-downloaded RNT files to SharePoint (avoids a second Wheelsys ZIP fetch on send).
     *
     * @param  list<array{name:string,contents:string,bytes?:int}>  $files
     * @return array{
     *     folder: string,
     *     uploaded: list<array{name:string,url:string,bytes:int}>,
     *     skipped: list<string>,
     *     count: int
     * }
     */
    public function archiveDownloadedFiles(FineProcessLog $log, array $files): array
    {
        $rentalId = $log->rental_id;
        $folder = $this->fineArchiver->documentFolderPathForLog($log);
        $this->sharePoint->ensureFolderExists($folder);

        $uploaded = [];
        $skipped = [];
        $urls = [];

        foreach ($files as $file) {
            $name = $this->sharePointFileName((string) ($file['name'] ?? 'rnt.pdf'), $log->agr_no);
            try {
                $result = $this->sharePoint->upload($name, (string) ($file['contents'] ?? ''), $folder);
                $url = trim((string) ($result['webUrl'] ?? ''));
                if ($url === '') {
                    $skipped[] = $name.' (empty URL)';
                    continue;
                }
                $uploaded[] = [
                    'name' => $name,
                    'url' => $url,
                    'bytes' => (int) ($file['bytes'] ?? strlen((string) ($file['contents'] ?? ''))),
                ];
                $urls[] = $url;
            } catch (Throwable $e) {
                $skipped[] = $name.': '.$e->getMessage();
            }
        }

        if ($uploaded === []) {
            throw new RuntimeException(
                'RNT attachment SharePoint upload failed'
                .($skipped !== [] ? ': '.implode('; ', $skipped) : '.')
            );
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $allUrls = is_array($log->sharepoint_urls) ? array_values($log->sharepoint_urls) : [];
        foreach ($urls as $url) {
            array_unshift($allUrls, $url);
        }
        $allUrls = array_values(array_unique(array_filter(
            $allUrls,
            static fn ($u): bool => is_string($u) && trim($u) !== ''
        )));

        $workflow['sharepoint_rnt_documents'] = [
            'folder' => $folder,
            'rental_id' => filled($rentalId) ? (int) $rentalId : null,
            'agr_no' => $log->agr_no,
            'urls' => $urls,
            'files' => array_map(static fn (array $u): string => $u['name'], $uploaded),
            'archived_at' => now()->toIso8601String(),
        ];

        $log->fill([
            'sharepoint_urls' => $allUrls,
            'attachments_uploaded' => max((int) $log->attachments_uploaded, count($allUrls)),
            'workflow' => $workflow,
        ])->save();

        return [
            'folder' => $folder,
            'uploaded' => $uploaded,
            'skipped' => $skipped,
            'count' => count($uploaded),
        ];
    }

    private function sharePointFileName(string $original, ?string $agrNo): string
    {
        $base = basename(str_replace('\\', '/', $original));
        $base = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $base);
        $base = trim($base);
        if ($base === '') {
            $base = 'rnt-document.pdf';
        }

        // Keep original signed-* names; prefix with rnt- for clarity in the fine folder.
        if (! str_starts_with(strtolower($base), 'rnt-')) {
            $base = 'rnt-'.$base;
        }

        $agrNo = trim((string) $agrNo);
        if ($agrNo !== '' && ! str_contains($base, $agrNo)) {
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $stem = pathinfo($base, PATHINFO_FILENAME);
            $base = $stem.'-'.$agrNo.($ext !== '' ? '.'.$ext : '');
        }

        return Str::limit($base, 160, '');
    }
}
