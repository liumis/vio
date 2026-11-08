<?php

namespace App\Support;

use App\Models\EmailSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MarkSign API client — ported from pakaitinis (same client / same token).
 */
final class MarkSignService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $cfg = EmailSetting::marksignConfig();
        $this->token = $cfg['token'];
        $this->baseUrl = rtrim($cfg['base_url'], '/');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    public function uploadDocument(string $pdfPath): string
    {
        if (! $this->isConfigured()) {
            throw new Exception('MarkSign access token is not configured (MARKSIGN_TOKEN / System settings).');
        }

        $fullPath = $this->resolveAbsolutePath($pdfPath);
        if (! is_readable($fullPath)) {
            throw new Exception("PDF file not found for upload: {$fullPath}");
        }

        $fileName = basename($fullPath);
        $fileContents = file_get_contents($fullPath);
        if ($fileContents === false) {
            throw new Exception("Could not read PDF: {$fullPath}");
        }

        $response = Http::timeout(90)
            ->asMultipart()
            ->post("{$this->baseUrl}/v2/document/upload", [
                ['name' => 'access_token', 'contents' => $this->token],
                ['name' => 'access', 'contents' => 'private'],
                ['name' => 'billing_type', 'contents' => 'document_owner'],
                [
                    'name' => 'files[]',
                    'contents' => $fileContents,
                    'filename' => $fileName,
                    'headers' => ['Content-Type' => 'application/pdf'],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('MarkSign upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('MarkSign Upload Error: '.$response->body());
        }

        $uuid = $response->json('data.0.uuid');
        if (! filled($uuid)) {
            throw new Exception('Nepavyko gauti dokumento UUID. Atsakymas: '.$response->body());
        }

        Log::info('MarkSign document uploaded', ['uuid' => $uuid]);

        return (string) $uuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocumentStatus(string $docUuid): array
    {
        $response = Http::timeout(30)->post("{$this->baseUrl}/document/{$docUuid}/signers", [
            'access_token' => $this->token,
        ]);

        if (! $response->successful()) {
            throw new Exception('MarkSign API Error: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function downloadSignedDocument(string $docUuid, string $purpose = 'chargeback'): ?string
    {
        $response = Http::timeout(90)->get("{$this->baseUrl}/document/{$docUuid}/download", [
            'access_token' => $this->token,
        ]);

        if (! $response->successful()) {
            Log::error('MarkSign Download Error: '.$response->body());

            return null;
        }

        $safePurpose = preg_replace('/[^a-z0-9_\-]+/i', '_', $purpose) ?: 'document';
        $filename = 'signed/'.$safePurpose.'_'.$docUuid.'_'.time().'.pdf';
        Storage::disk('local')->put($filename, $response->body());

        return $filename;
    }

    /**
     * @param  array{name:string,surname:string,email:string}  $signer
     */
    public function generateSigningLink(string $docUuid, array $signer): string
    {
        $callbackUrl = route('marksign.callback');

        Log::info('MarkSign generate signing link', [
            'uuid' => $docUuid,
            'callback_url' => $callbackUrl,
            'signer_email' => $signer['email'] ?? null,
        ]);

        $response = Http::timeout(60)->post("{$this->baseUrl}/v2/document/generate-temporary-signing-link", [
            'access_token' => $this->token,
            'documents' => [(string) $docUuid],
            'callback_url' => $callbackUrl,
            'language' => 'lt',
            'signers' => [[
                'name' => $signer['name'],
                'surname' => $signer['surname'],
                'email' => $signer['email'],
            ]],
        ]);

        if (! $response->successful()) {
            Log::error('MarkSign signing link failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('MarkSign Link Error: '.$response->body());
        }

        $url = $response->json('temporary_signing_links.0.temporary_signing_link');
        if (! filled($url)) {
            throw new Exception('Nepavyko rasti temporary_signing_link atsakyme: '.$response->body());
        }

        return (string) $url;
    }

    private function resolveAbsolutePath(string $pdfPath): string
    {
        if (is_file($pdfPath)) {
            return $pdfPath;
        }

        $disk = Storage::disk('local');
        if ($disk->exists($pdfPath)) {
            return $disk->path($pdfPath);
        }

        return $pdfPath;
    }
}
