<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin OCR.space client (same provider as Futura/SS InvoiceOcrService).
 */
final class OcrSpaceClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.ocr_space.key'));
    }

    /**
     * OCR a local image/PDF. Returns plain text or empty string.
     */
    /**
     * OCR.space Engine 2: use eng (reads LT ANR scans well). `lit` is rejected by the API.
     */
    public function extractText(string $absoluteFilePath, string $language = 'eng'): string
    {
        $apiKey = trim((string) config('services.ocr_space.key', ''));
        if ($apiKey === '' || ! is_file($absoluteFilePath)) {
            return '';
        }

        $extension = strtoupper((string) pathinfo($absoluteFilePath, PATHINFO_EXTENSION)) ?: 'PDF';

        try {
            $response = Http::timeout(90)
                ->asMultipart()
                ->post('https://api.ocr.space/parse/image', [
                    ['name' => 'apikey', 'contents' => $apiKey],
                    ['name' => 'language', 'contents' => $language],
                    ['name' => 'OCREngine', 'contents' => '2'],
                    ['name' => 'scale', 'contents' => 'true'],
                    ['name' => 'detectOrientation', 'contents' => 'true'],
                    ['name' => 'isOverlayRequired', 'contents' => 'false'],
                    ['name' => 'isTable', 'contents' => 'true'],
                    ['name' => 'filetype', 'contents' => $extension],
                    [
                        'name' => 'file',
                        'contents' => fopen($absoluteFilePath, 'r'),
                        'filename' => basename($absoluteFilePath),
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('OCR.space request failed', [
                'file' => basename($absoluteFilePath),
                'error' => $e->getMessage(),
            ]);

            return '';
        }

        if (! $response->ok()) {
            Log::warning('OCR.space HTTP error', [
                'file' => basename($absoluteFilePath),
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 400),
            ]);

            return '';
        }

        $payload = $response->json();
        if (data_get($payload, 'IsErroredOnProcessing')) {
            $ocrError = data_get($payload, 'ErrorMessage.0', data_get($payload, 'ErrorMessage', ''));
            if (is_array($ocrError)) {
                $ocrError = implode(' ', $ocrError);
            }
            Log::warning('OCR.space API error', [
                'file' => basename($absoluteFilePath),
                'message' => (string) $ocrError,
            ]);

            return '';
        }

        $parts = [];
        foreach ((array) data_get($payload, 'ParsedResults', []) as $result) {
            if (! is_array($result)) {
                continue;
            }
            $text = trim((string) ($result['ParsedText'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode("\n\n", $parts));
    }
}
