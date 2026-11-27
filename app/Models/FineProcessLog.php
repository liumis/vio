<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FineProcessLog extends Model
{
    protected $fillable = [
        'graph_message_id',
        'mailbox',
        'from_email',
        'subject',
        'received_at',
        'authority_id',
        'authority_name',
        'vehicle',
        'reference',
        'ticket_date',
        'ticket_time',
        'agr_no',
        'rental_id',
        'driver_name',
        'status',
        'attachments_uploaded',
        'sharepoint_urls',
        'local_attachment_dir',
        'workflow',
        'wheels_export_status',
        'wheels_export_batch',
        'wheels_export_file_name',
        'wheels_exported_at',
        'wheels_exported_by',
        'wheels_export_sharepoint_url',
        'wheels_import_status',
        'wheels_imported_at',
        'wheels_imported_by',
        'wheels_violation_id',
        'wheels_verify_detail',
        'wheels_tv_state',
        'wheels_paid_date',
        'email_to',
        'email_sent_at',
        'moved_to_processed',
        'processed_folder',
        'error',
        'processed_by',
        'processed_at',
        'chargeback_status',
        'chargeback_data',
        'chargeback_pdf_path',
        'marksign_uuid',
        'marksign_purpose',
        'marksign_signing_url',
        'marksign_signed_pdf_path',
        'chargeback_marked_at',
        'chargeback_marked_by',
        'paid_at',
        'paid_marked_by',
        'debt_list_marked_at',
        'debt_list_marked_by',
    ];

    public const WHEELS_EXPORT_PENDING = 'pending';

    public const WHEELS_EXPORT_EXPORTED = 'exported';

    public const WHEELS_EXPORT_SKIPPED = 'skipped';

    /** Date-only fine with 2+ drivers that day — blocked until human picks rental. */
    public const WHEELS_EXPORT_NEEDS_REVIEW = 'needs_review';

    public const WHEELS_IMPORT_AWAITING = 'awaiting';

    public const WHEELS_IMPORT_IMPORTED = 'imported';

    /** Import file prepared but Wheelsys scan did not find a matching TV. */
    public const WHEELS_IMPORT_MISSING = 'missing';

    /** Charge deduction failed — notice prepared / awaiting MarkSign. */
    public const CHARGEBACK_AWAITING_SIGNATURE = 'awaiting_signature';

    public const CHARGEBACK_SIGNED = 'signed';

    public const CHARGEBACK_NOTICE_SENT = 'notice_sent';

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'processed_at' => 'datetime',
            'wheels_exported_at' => 'datetime',
            'wheels_imported_at' => 'datetime',
            'chargeback_marked_at' => 'datetime',
            'paid_at' => 'datetime',
            'debt_list_marked_at' => 'datetime',
            'wheels_paid_date' => 'date',
            'ticket_date' => 'date',
            'sharepoint_urls' => 'array',
            'workflow' => 'array',
            'chargeback_data' => 'array',
            'moved_to_processed' => 'boolean',
            'attachments_uploaded' => 'integer',
            'rental_id' => 'integer',
        ];
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isMarkedForDebtList(): bool
    {
        return $this->debt_list_marked_at !== null;
    }

    /**
     * Primary fine notice PDF on SharePoint (never an RNT / contract file).
     */
    public function documentSharePointUrl(): ?string
    {
        $workflow = is_array($this->workflow) ? $this->workflow : [];
        $stored = trim((string) (($workflow['sharepoint_document']['url'] ?? '') ?: ''));
        if ($stored !== '' && ! $this->isRntSharePointUrl($stored)) {
            return $stored;
        }

        $urls = is_array($this->sharepoint_urls) ? $this->sharepoint_urls : [];
        $fallback = null;
        foreach ($urls as $url) {
            if (! is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if ($this->isRntSharePointUrl($url)) {
                continue;
            }
            // Prefer file whose name contains the fine reference.
            $ref = trim((string) ($this->reference ?? ''));
            $path = urldecode((string) (parse_url($url, PHP_URL_PATH) ?: ''));
            if ($ref !== '' && $path !== '' && str_contains($path, $ref)) {
                return $url;
            }
            $fallback ??= $url;
        }

        return $fallback;
    }

    /**
     * Rental contract (RNT) files archived from Wheelsys → SharePoint.
     *
     * @return list<string>
     */
    public function rntContractSharePointUrls(): array
    {
        $workflow = is_array($this->workflow) ? $this->workflow : [];
        $stored = is_array($workflow['sharepoint_rnt_documents']['urls'] ?? null)
            ? $workflow['sharepoint_rnt_documents']['urls']
            : [];
        $out = [];
        foreach ($stored as $url) {
            if (is_string($url) && trim($url) !== '') {
                $out[] = trim($url);
            }
        }
        if ($out !== []) {
            return array_values(array_unique($out));
        }

        $urls = is_array($this->sharepoint_urls) ? $this->sharepoint_urls : [];
        foreach ($urls as $url) {
            if (is_string($url) && $this->isRntSharePointUrl($url)) {
                $out[] = trim($url);
            }
        }

        return array_values(array_unique($out));
    }

    public function primaryRntContractSharePointUrl(): ?string
    {
        $urls = $this->rntContractPdfSharePointUrls();

        return $urls[0] ?? null;
    }

    /**
     * All RNT contract PDFs (signed first). Images from the rental Dropzone are excluded.
     *
     * @return list<string>
     */
    public function rntContractPdfSharePointUrls(): array
    {
        $urls = $this->rntContractSharePointUrls();
        $signed = [];
        $otherPdf = [];

        foreach ($urls as $url) {
            $path = strtolower(urldecode((string) (parse_url($url, PHP_URL_PATH) ?: '')));
            if (! str_ends_with($path, '.pdf')) {
                continue;
            }
            if (str_contains($path, 'signed-')) {
                $signed[] = $url;
            } else {
                $otherPdf[] = $url;
            }
        }

        return array_values(array_unique(array_merge($signed, $otherPdf)));
    }

    /**
     * Short label for a SharePoint RNT URL button (e.g. Contract, Contract 2, Depo).
     */
    public static function rntContractButtonLabel(string $url, int $index, int $total): string
    {
        $base = basename(urldecode((string) (parse_url($url, PHP_URL_PATH) ?: 'contract.pdf')));
        $base = preg_replace('/^rnt-/i', '', $base) ?: $base;
        $stem = pathinfo($base, PATHINFO_FILENAME);
        $stem = preg_replace('/-RATLL-\d+$/i', '', (string) $stem) ?: (string) $stem;
        $stem = preg_replace('/-RNT-\d+$/i', '', $stem) ?: $stem;

        if (str_starts_with(strtolower($stem), 'signed-')) {
            return $total > 1 ? 'Contract '.($index + 1) : 'Contract';
        }

        $short = \Illuminate\Support\Str::limit($stem, 18, '…');

        return $short !== '' ? $short : ($total > 1 ? 'Contract '.($index + 1) : 'Contract');
    }

    private function isRntSharePointUrl(string $url): bool
    {
        $path = strtolower(basename(urldecode((string) (parse_url($url, PHP_URL_PATH) ?: ''))));

        return str_starts_with($path, 'rnt-')
            || str_contains($path, 'signed-d20');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wheels_imported_by');
    }

    public function isReadyForWheelsExport(): bool
    {
        return filled($this->vehicle) && (filled($this->reference) || filled($this->ticket_date));
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wheels_exported_by');
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(Authority::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
