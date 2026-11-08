<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use App\Support\LatviaAuthorityPackageService;
use App\Support\MarkSignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarkSignCallbackController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('MarkSign Callback', $request->all());

        $docUuid = (string) $request->input('uuid');
        $status = (string) $request->input('status');

        if ($status === 'signed' && $docUuid !== '') {
            $log = FineProcessLog::query()->where('marksign_uuid', $docUuid)->first();

            if ($log) {
                try {
                    $purpose = (string) ($log->marksign_purpose ?: 'chargeback');
                    $path = app(MarkSignService::class)->downloadSignedDocument($docUuid, $purpose);
                    if ($path) {
                        $log->marksign_signed_pdf_path = $path;
                    }
                } catch (\Throwable $e) {
                    Log::error('MarkSign signed PDF download failed: '.$e->getMessage());
                }

                if ($log->marksign_purpose === LatviaAuthorityPackageService::PURPOSE_LETTER) {
                    $workflow = is_array($log->workflow) ? $log->workflow : [];
                    $package = is_array($workflow['latvia_package'] ?? null) ? $workflow['latvia_package'] : [];
                    $package['letter_status'] = 'signed';
                    $package['signed_at'] = now()->toIso8601String();
                    $workflow['latvia_package'] = $package;
                    $log->workflow = $workflow;
                    $log->save();
                    Log::info("FineProcessLog #{$log->id} Latvia letter marked signed.");
                } else {
                    $log->chargeback_status = FineProcessLog::CHARGEBACK_SIGNED;
                    $log->save();
                    Log::info("FineProcessLog #{$log->id} chargeback notice marked signed.");
                }
            } else {
                $settings = EmailSetting::query()
                    ->where('power_of_attorney_marksign_uuid', $docUuid)
                    ->first();

                if ($settings) {
                    try {
                        $path = app(MarkSignService::class)->downloadSignedDocument(
                            $docUuid,
                            LatviaAuthorityPackageService::PURPOSE_POA
                        );
                        if ($path) {
                            app(LatviaAuthorityPackageService::class)->markPowerOfAttorneySigned($path);
                            Log::info('Power of attorney marked signed.', ['path' => $path]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('POA MarkSign download failed: '.$e->getMessage());
                    }
                } else {
                    Log::warning("MarkSign callback: no FineProcessLog/POA for UUID {$docUuid}");
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
