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
    ];

    public const WHEELS_EXPORT_PENDING = 'pending';

    public const WHEELS_EXPORT_EXPORTED = 'exported';

    public const WHEELS_EXPORT_SKIPPED = 'skipped';

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
            'ticket_date' => 'date',
            'sharepoint_urls' => 'array',
            'workflow' => 'array',
            'chargeback_data' => 'array',
            'moved_to_processed' => 'boolean',
            'attachments_uploaded' => 'integer',
            'rental_id' => 'integer',
        ];
    }

    /**
     * Primary fine PDF on SharePoint (not the Wheelsys Excel import workbook).
     */
    public function documentSharePointUrl(): ?string
    {
        $urls = is_array($this->sharepoint_urls) ? $this->sharepoint_urls : [];
        foreach ($urls as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
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
