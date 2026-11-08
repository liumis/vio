<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Builds a Wheelsys "Traffic violation" Import Data workbook
 * matching TrafficViolationTemplate.xlsx column order / cell types.
 * Uploads the workbook to SharePoint; local temp is only for the browser download.
 */
final class WheelsysTrafficViolationExcelExporter
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'Reference No',
        'Ticket Type',
        'Ticket Date',
        'Ticket Time',
        'Vehicle',
        'Station',
        'State',
        'Issued by',
        'Authority',
        'Address',
        'City',
        'Zip Code',
        'Traffic Charge',
        'Admin Fee',
        'Hearing Date',
        'Payment Date',
        'Fine Received Date',
    ];

    /**
     * Columns that Wheelsys validates as Excel date/datetime (not plain text).
     *
     * @var list<string>
     */
    private const DATE_HEADERS = [
        'Ticket Date',
        'Hearing Date',
        'Payment Date',
        'Fine Received Date',
    ];

    /**
     * @var list<string>
     */
    private const TIME_HEADERS = [
        'Ticket Time',
    ];

    public function __construct(
        private readonly MicrosoftGraphSharePoint $sharePoint = new MicrosoftGraphSharePoint,
    ) {
    }

    /**
     * Write Excel, upload to SharePoint, mark logs exported + awaiting Wheels import.
     * Caller should stream `$path` to the browser with deleteFileAfterSend(true).
     *
     * @param  Collection<int, FineProcessLog>|iterable<FineProcessLog>  $logs
     * @return array{
     *     path: string,
     *     batch: string,
     *     count: int,
     *     sharepoint_url: string,
     *     sharepoint_folder: string,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function exportUploadAndMark(iterable $logs, ?int $userId = null, ?string $batch = null): array
    {
        $collection = $logs instanceof Collection ? $logs : collect($logs);
        $collection = $collection->filter(static fn ($log): bool => $log instanceof FineProcessLog)->values();
        if ($collection->isEmpty()) {
            throw new RuntimeException('No rows to export.');
        }

        // Ensure each fine PDF is archived as "{Driver} {Reference}" before Excel export.
        // JUDU/Unipark/Parkavimas: Print email → PDF first when the notice was letter-only.
        $archiver = app(IncomingFineDocumentArchiver::class);
        $emailPrint = app(IncomingFineEmailPrintPdf::class);
        foreach ($collection as $log) {
            try {
                if ($emailPrint->isLetterFormAuthority($log->authority, $log->from_email, $log->authority_name)) {
                    $emailPrint->ensureAndArchive($log);
                } else {
                    $archiver->archive($log);
                }
            } catch (Throwable) {
                // Keep export going; Document link may stay empty for that row.
            }
        }

        $written = $this->writeToTemp($collection, $batch);
        $fileName = 'wheels-export-'.$written['batch'].'.xlsx';

        try {
            $contents = file_get_contents($written['path']);
            if ($contents === false) {
                throw new RuntimeException('Could not read the prepared import temp file.');
            }

            $folder = $this->resolveExportFolder();
            $upload = $this->sharePoint->upload($fileName, $contents, $folder);
        } catch (Throwable $e) {
            @unlink($written['path']);

            throw $e;
        }

        $sharePointUrl = (string) ($upload['webUrl'] ?? '');
        $now = now();

        foreach ($collection as $log) {
            $workflow = is_array($log->workflow) ? $log->workflow : [];
            $steps = is_array($workflow['steps'] ?? null) ? $workflow['steps'] : [];
            $detail = 'Import batch '.$written['batch']
                .($sharePointUrl !== '' ? ' · SharePoint saved' : '');
            $updated = false;
            foreach ($steps as $i => $step) {
                if (($step['key'] ?? '') === 'wheels_create') {
                    $steps[$i]['ok'] = true;
                    $steps[$i]['detail'] = $detail;
                    $updated = true;
                }
            }
            if (! $updated) {
                $steps[] = ['key' => 'wheels_create', 'ok' => true, 'detail' => $detail];
            }
            $workflow['steps'] = $steps;
            $workflow['wheels_export'] = [
                'batch' => $written['batch'],
                'sharepoint_url' => $sharePointUrl,
                'sharepoint_folder' => $folder,
                'file_name' => $fileName,
                'row_count' => $written['count'],
                'exported_at' => $now->toIso8601String(),
            ];

            $log->fill([
                'wheels_export_status' => FineProcessLog::WHEELS_EXPORT_EXPORTED,
                'wheels_export_batch' => $written['batch'],
                'wheels_export_file_name' => $fileName,
                'wheels_exported_at' => $now,
                'wheels_exported_by' => $userId,
                'wheels_export_sharepoint_url' => $sharePointUrl !== '' ? $sharePointUrl : null,
                'wheels_import_status' => FineProcessLog::WHEELS_IMPORT_AWAITING,
                'wheels_imported_at' => null,
                'wheels_imported_by' => null,
                'wheels_violation_id' => null,
                'wheels_verify_detail' => null,
                'workflow' => $workflow,
            ])->save();
        }

        ActivityLogger::log(
            'Wheels import file prepared: '.$written['batch'].' ('.$written['count'].' row(s))',
            meta: [
                'batch' => $written['batch'],
                'count' => $written['count'],
                'sharepoint_url' => $sharePointUrl,
                'sharepoint_folder' => $folder,
                'file_name' => $fileName,
                'fine_process_log_ids' => $collection->pluck('id')->values()->all(),
            ]
        );

        return [
            'path' => $written['path'],
            'batch' => $written['batch'],
            'count' => $written['count'],
            'file_name' => $fileName,
            'sharepoint_url' => $sharePointUrl,
            'sharepoint_folder' => $folder,
            'rows' => $written['rows'],
        ];
    }

    /**
     * @param  Collection<int, FineProcessLog>|iterable<FineProcessLog>  $logs
     * @return array{path: string, batch: string, count: int, rows: list<array<string, mixed>>}
     */
    public function writeToTemp(iterable $logs, ?string $batch = null): array
    {
        $batch = $batch ?: ('vio-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)));
        $rows = [];

        foreach ($logs as $log) {
            if (! $log instanceof FineProcessLog) {
                continue;
            }
            $rows[] = $this->mapLog($log);
        }

        if ($rows === []) {
            throw new RuntimeException('No rows to export.');
        }

        $spreadsheet = $this->makeWorkbook();
        $sheet = $spreadsheet->getSheetByName('Traffic Violations')
            ?? $spreadsheet->getActiveSheet();
        $sheet->setTitle('Traffic Violations');

        // Ensure header row matches the official template order.
        foreach (self::HEADERS as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        // Drop any leftover sample / previous data rows from the template.
        $highestRow = max(2, (int) $sheet->getHighestRow());
        if ($highestRow >= 2) {
            $sheet->removeRow(2, $highestRow - 1);
        }

        foreach ($rows as $i => $row) {
            $r = $i + 2;
            foreach (self::HEADERS as $col => $header) {
                $this->writeMappedCell($sheet, $col + 1, $r, $header, $row[$header] ?? null);
            }
        }

        $dir = storage_path('app/private/wheels-exports');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create wheels-exports directory.');
        }

        $path = $dir.DIRECTORY_SEPARATOR.'wheels-export-'.$batch.'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $path,
            'batch' => $batch,
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Prefer {configured}/WheelsExports when that folder exists; else configured folder.
     */
    public function resolveExportFolder(): string
    {
        $base = EmailSetting::sharePointConfig()['folder_path'];
        $preferred = trim($base.'/WheelsExports', '/');

        try {
            $this->sharePoint->ensureFolderExists($preferred);

            return $preferred;
        } catch (Throwable) {
            return $base;
        }
    }

    /**
     * Download a previously uploaded import workbook from SharePoint.
     */
    public function downloadImportFile(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        if ($fileName === '') {
            throw new RuntimeException('Import file name is empty.');
        }

        $folder = $this->resolveExportFolder();

        try {
            return $this->sharePoint->download($fileName, $folder);
        } catch (Throwable $e) {
            // Older uploads may sit in the base SharePoint folder.
            $base = EmailSetting::sharePointConfig()['folder_path'];
            if ($base !== '' && $base !== $folder) {
                return $this->sharePoint->download($fileName, $base);
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function mapLog(FineProcessLog $log): array
    {
        $ticketDate = $log->ticket_date
            ? $log->ticket_date->copy()->startOfDay()
            : null;

        $ticketTime = $this->parseTicketTime($log->ticket_time, $ticketDate);

        $fineReceived = null;
        if ($log->received_at) {
            $fineReceived = $log->received_at->timezone(config('app.timezone'))->startOfDay();
        } elseif ($log->processed_at) {
            $fineReceived = $log->processed_at->timezone(config('app.timezone'))->startOfDay();
        }

        $ticketType = trim((string) config('services.wheelsys.default_ticket_type', 'Administration violation'));
        $station = trim((string) config('services.wheelsys.default_station', ''));
        $state = trim((string) config('services.wheelsys.default_ticket_state', ''));

        return [
            'Reference No' => (string) ($log->reference ?? ''),
            'Ticket Type' => $ticketType,
            'Ticket Date' => $ticketDate,
            'Ticket Time' => $ticketTime,
            'Vehicle' => strtoupper((string) ($log->vehicle ?? '')),
            'Station' => $station,
            'State' => $state,
            'Issued by' => '',
            'Authority' => (string) ($log->authority_name ?? ''),
            'Address' => '',
            'City' => '',
            'Zip Code' => '',
            'Traffic Charge' => '',
            'Admin Fee' => '',
            'Hearing Date' => null,
            'Payment Date' => null,
            'Fine Received Date' => $fineReceived,
        ];
    }

    private function makeWorkbook(): Spreadsheet
    {
        $template = resource_path('wheelsys/TrafficViolationTemplate.xlsx');
        if (is_file($template)) {
            return IOFactory::load($template);
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Traffic Violations');

        return $spreadsheet;
    }

    private function writeMappedCell(Worksheet $sheet, int $col, int $row, string $header, mixed $value): void
    {
        if ($value instanceof CarbonInterface) {
            if (in_array($header, self::TIME_HEADERS, true)) {
                // Time-only Excel serial (fraction of a day) — Wheelsys rejects plain text "12:00".
                $serial = ((int) $value->format('H') * 3600
                    + (int) $value->format('i') * 60
                    + (int) $value->format('s')) / 86400;
                $sheet->setCellValue([$col, $row], $serial);
                $sheet->getStyle([$col, $row])
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_DATE_TIME3); // h:mm

                return;
            }

            if (in_array($header, self::DATE_HEADERS, true)) {
                $sheet->setCellValue([$col, $row], ExcelDate::PHPToExcel($value->copy()->startOfDay()));
                $sheet->getStyle([$col, $row])
                    ->getNumberFormat()
                    ->setFormatCode('yyyy-mm-dd');

                return;
            }

            $value = $value->toDateString();
        }

        if ($value === null || $value === '') {
            $sheet->setCellValueExplicit([$col, $row], '', DataType::TYPE_STRING);

            return;
        }

        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue([$col, $row], $value);

            return;
        }

        $sheet->setCellValueExplicit([$col, $row], (string) $value, DataType::TYPE_STRING);
    }

    private function parseTicketTime(mixed $raw, ?CarbonInterface $ticketDate): ?CarbonInterface
    {
        $ticketTime = trim((string) ($raw ?? ''));
        if ($ticketTime === '') {
            return null;
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $ticketTime, $m)) {
            return null;
        }

        $base = $ticketDate
            ? $ticketDate->copy()->startOfDay()
            : Carbon::today(config('app.timezone'))->startOfDay();

        return $base->setTime((int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
    }
}
