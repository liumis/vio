<?php

namespace App\Support;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;
use Throwable;

/**
 * CHARGEBACKS/DEBTS tracker (instruction: register after client notice is sent).
 * Working copy lives on SharePoint; Google Sheet is the original client source.
 */
final class DebtTrackerService
{
    public const DEFAULT_GOOGLE_URL = 'https://docs.google.com/spreadsheets/d/1oQr8h3ecQHguDv03sm34YQ649fjrWnr_7awVHFub7LU/edit#gid=1707280998';

    public const DEFAULT_FILE_NAME = 'CHARGEBACKS-DEBTS.xlsx';

    public const DEFAULT_SHEET = 'LT debt';

    public function __construct(
        private readonly MicrosoftGraphSharePoint $sharePoint = new MicrosoftGraphSharePoint,
    ) {
    }

    /**
     * @return array{file_name:string,sheet:string,google_url:string,sharepoint_url:string}
     */
    public static function config(): array
    {
        $settings = EmailSetting::query()->first();

        return [
            'file_name' => trim((string) ($settings?->debt_tracker_file_name ?: self::DEFAULT_FILE_NAME)) ?: self::DEFAULT_FILE_NAME,
            'sheet' => trim((string) ($settings?->debt_tracker_sheet ?: self::DEFAULT_SHEET)) ?: self::DEFAULT_SHEET,
            'google_url' => trim((string) ($settings?->debt_tracker_google_url ?: self::DEFAULT_GOOGLE_URL)),
            'sharepoint_url' => trim((string) ($settings?->debt_tracker_url ?: '')),
        ];
    }

    /**
     * Upload a local workbook copy to SharePoint and store the web URL in settings.
     *
     * @return array{webUrl:string,name:string,id:string}
     */
    public function uploadWorkingCopy(?string $localAbsolutePath = null): array
    {
        $cfg = self::config();
        $path = $localAbsolutePath;
        if ($path === null || $path === '' || ! is_file($path)) {
            $path = Storage::disk('local')->path('chargebacks-debts-source.xlsx');
        }
        if (! is_file($path)) {
            throw new RuntimeException('Local CHARGEBACKS/DEBTS source file not found. Export/download it first.');
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Could not read CHARGEBACKS/DEBTS source file.');
        }

        $upload = $this->sharePoint->upload($cfg['file_name'], $contents);
        $url = trim((string) ($upload['webUrl'] ?? ''));

        $settings = EmailSetting::current();
        $settings->fill([
            'debt_tracker_url' => $url !== '' ? $url : $settings->debt_tracker_url,
            'debt_tracker_google_url' => $settings->debt_tracker_google_url ?: self::DEFAULT_GOOGLE_URL,
            'debt_tracker_sheet' => $settings->debt_tracker_sheet ?: self::DEFAULT_SHEET,
            'debt_tracker_file_name' => $cfg['file_name'],
        ])->save();

        return [
            'webUrl' => $url,
            'name' => (string) ($upload['name'] ?? $cfg['file_name']),
            'id' => (string) ($upload['id'] ?? ''),
        ];
    }

    /**
     * Append one LT DEBT row after client notice is sent (instruction columns).
     *
     * @param  array{
     *     document_no?: string,
     *     client_name: string,
     *     rental_no?: string,
     *     inv_number?: string,
     *     amount: float|int|string,
     *     sent_at?: string|\DateTimeInterface|null,
     *     due_at?: string|\DateTimeInterface|null,
     *     paid?: string|null,
     *     legalbalance_at?: string|\DateTimeInterface|null,
     *     comment?: string|null
     * }  $row
     * @return array{row:int,sharepoint_url:string}
     */
    public function appendLtDebtRow(array $row): array
    {
        $cfg = self::config();
        $fileName = $cfg['file_name'];
        $sheetName = $cfg['sheet'];

        $localRelative = 'debt-tracker/working-'.now()->format('Ymd-His').'.xlsx';
        Storage::disk('local')->makeDirectory('debt-tracker');

        try {
            $binary = $this->sharePoint->download($fileName);
            Storage::disk('local')->put($localRelative, $binary);
        } catch (Throwable $e) {
            $fallback = Storage::disk('local')->path('chargebacks-debts-source.xlsx');
            if (! is_file($fallback)) {
                throw new RuntimeException(
                    'SharePoint debt tracker download failed and no local source exists: '.$e->getMessage(),
                    0,
                    $e
                );
            }
            Storage::disk('local')->put($localRelative, file_get_contents($fallback) ?: '');
        }

        $absolute = Storage::disk('local')->path($localRelative);
        $wb = IOFactory::load($absolute);
        $sheet = $wb->getSheetByName($sheetName);
        if ($sheet === null) {
            throw new RuntimeException("Sheet \"{$sheetName}\" not found in debt tracker workbook.");
        }

        $nextRow = ((int) $sheet->getHighestDataRow()) + 1;
        $sent = $this->toDate($row['sent_at'] ?? now());
        $due = $this->toDate($row['due_at'] ?? $sent->copy()->addDays(30));
        $lb = isset($row['legalbalance_at']) && $row['legalbalance_at']
            ? $this->toDate($row['legalbalance_at'])
            : $sent->copy()->addDays(120);

        $amount = (float) ($row['amount'] ?? 0);
        $amountLabel = '€'.number_format($amount, 2, '.', '');

        $values = [
            1 => trim((string) ($row['document_no'] ?? $row['rental_no'] ?? '')),
            2 => trim((string) ($row['client_name'] ?? '')),
            3 => trim((string) ($row['rental_no'] ?? '')),
            4 => trim((string) ($row['inv_number'] ?? '')),
            5 => $amountLabel,
            6 => $sent->format('Y-m-d'),
            7 => $due->format('Y-m-d'),
            8 => trim((string) ($row['paid'] ?? 'Ne')) ?: 'Ne',
            9 => $lb->format('Y-m-d'),
            10 => '',
            11 => trim((string) ($row['comment'] ?? '')),
        ];

        foreach ($values as $col => $value) {
            $coord = Coordinate::stringFromColumnIndex($col).$nextRow;
            $sheet->setCellValue($coord, $value);
        }

        $writer = IOFactory::createWriter($wb, 'Xlsx');
        $writer->save($absolute);

        $upload = $this->sharePoint->upload($fileName, file_get_contents($absolute) ?: '');
        $url = trim((string) ($upload['webUrl'] ?? $cfg['sharepoint_url']));

        if ($url !== '') {
            EmailSetting::current()->fill(['debt_tracker_url' => $url])->save();
        }

        return [
            'row' => $nextRow,
            'sharepoint_url' => $url,
        ];
    }

    /**
     * Register a chargeback notice into LT DEBT after it was sent to the client.
     */
    public function registerSentNotice(FineProcessLog $log, ?string $invNumber = null, ?string $comment = null): array
    {
        $data = is_array($log->chargeback_data) ? $log->chargeback_data : [];
        $client = trim((string) ($data['client_name'] ?? $log->driver_name ?? ''));
        $rental = trim((string) ($data['rental_no'] ?? $log->agr_no ?? ''));
        $reference = trim((string) ($data['reference'] ?? $log->reference ?? ''));
        $amount = (float) ($data['total_amount'] ?? (($data['fine_amount'] ?? 0) + ($data['admin_fee'] ?? 0)));
        $inv = trim((string) ($invNumber ?? $data['inv_number'] ?? ''));

        if ($client === '') {
            throw new RuntimeException('Client name is required to register LT DEBT.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Amount is required to register LT DEBT.');
        }

        $documentNo = $rental !== '' ? trim($rental.' Notice') : ($reference !== '' ? $reference : 'Notice');

        $result = $this->appendLtDebtRow([
            'document_no' => $documentNo,
            'client_name' => $client,
            'rental_no' => $rental,
            'inv_number' => $inv,
            'amount' => $amount,
            'sent_at' => now(),
            'paid' => 'Ne',
            'comment' => $comment ?? ('VIO #'.$log->id.($reference !== '' ? ' · '.$reference : '')),
        ]);

        $data['inv_number'] = $inv;
        $data['lt_debt_row'] = $result['row'];
        $data['lt_debt_registered_at'] = now()->toIso8601String();

        $log->fill([
            'chargeback_status' => FineProcessLog::CHARGEBACK_NOTICE_SENT,
            'chargeback_data' => $data,
        ])->save();

        return $result;
    }

    private function toDate(mixed $value): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))
                ->timezone(config('app.timezone'))
                ->startOfDay();
        }

        return Carbon::parse((string) $value, config('app.timezone'))->startOfDay();
    }
}
