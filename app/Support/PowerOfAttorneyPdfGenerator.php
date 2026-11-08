<?php

namespace App\Support;

use App\Models\EmailSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Company power of attorney for Latvian CPP replies (instruction: 1 year validity).
 */
final class PowerOfAttorneyPdfGenerator
{
    /**
     * @return array{relative_path:string,absolute_path:string,file_name:string,valid_from:string,valid_until:string}
     */
    public function generate(?string $validFrom = null, ?string $validUntil = null): array
    {
        $signer = EmailSetting::marksignSigner();
        $from = $validFrom
            ? \Illuminate\Support\Carbon::parse($validFrom)->startOfDay()
            : now()->timezone(config('app.timezone'))->startOfDay();
        $until = $validUntil
            ? \Illuminate\Support\Carbon::parse($validUntil)->startOfDay()
            : $from->copy()->addYear()->subDay();

        $payload = [
            'companyName' => 'UAB BMS Technologija',
            'companyCode' => '300138495',
            'companyAddress' => 'A. Juozapavičiaus 10A-4, Vilnius, LT-09311, Lietuvos Respublika',
            'companyPhone' => '+370 600 23699',
            'companyEmail' => 'administration@greenlease.lt',
            'signerTitle' => 'Direktorius',
            'signerFullName' => $signer['full_name'],
            'documentDate' => $from->format('Y-m-d'),
            'validFrom' => $from->format('Y-m-d'),
            'validUntil' => $until->format('Y-m-d'),
            'authorityName' => 'Valsts policija / Ceļu policija (Latvija)',
            'authorityEmail' => 'kanceleja.cpp@vp.gov.lv',
        ];

        $binary = Pdf::loadView('pdfs.power-of-attorney', $payload)->setPaper('a4')->output();
        if ($binary === '' || $binary === false) {
            throw new RuntimeException('Failed to generate power of attorney PDF.');
        }

        $fileName = 'igaliojimas-'.$from->format('Ymd').'-'.$until->format('Ymd').'.pdf';
        $relative = 'power-of-attorney/'.$fileName;
        Storage::disk('local')->makeDirectory('power-of-attorney');
        Storage::disk('local')->put($relative, $binary);

        return [
            'relative_path' => $relative,
            'absolute_path' => Storage::disk('local')->path($relative),
            'file_name' => $fileName,
            'valid_from' => $from->format('Y-m-d'),
            'valid_until' => $until->format('Y-m-d'),
        ];
    }
}
