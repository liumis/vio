<?php

namespace App\Support;

use App\Models\EmailSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MicrosoftGraphMailer
{
    /**
     * @param  list<string|array{path:string,name?:string,content_type?:string}>|null  $attachments
     *         Absolute paths, local-disk relative paths, or structured attachment descriptors.
     */
    public function sendHtml(
        string $fromEmail,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $replyTo = null,
        ?string $attachmentPath = null,
        ?string $inlineLogoPath = null,
        ?string $inlineLogoCid = null,
        ?array $attachments = null,
    ): void {
        $config = EmailSetting::graphConfig();
        $tenantId = $config['tenant_id'];
        $clientId = $config['client_id'];
        $clientSecret = $config['client_secret'];

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Microsoft 365 OAuth is not configured. Save Tenant ID, Client ID, and Client secret in System settings (or set O365_* env vars).');
        }

        if ($fromEmail === '') {
            throw new RuntimeException('From email is required for Microsoft 365 sending.');
        }

        $tokenResponse = Http::asForm()
            ->timeout(20)
            ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Failed to get Microsoft Graph token: '.$tokenResponse->body());
        }

        $accessToken = (string) ($tokenResponse->json('access_token') ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Microsoft Graph token response did not include access_token.');
        }

        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $htmlBody,
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $toEmail,
                    ],
                ],
            ],
        ];

        if ($replyTo !== null && trim($replyTo) !== '') {
            $message['replyTo'] = [[
                'emailAddress' => [
                    'address' => trim($replyTo),
                ],
            ]];
        }

        $graphAttachments = [];

        $all = is_array($attachments) ? $attachments : [];
        if ($attachmentPath !== null && $attachmentPath !== '') {
            array_unshift($all, $attachmentPath);
        }

        foreach ($all as $item) {
            $built = $this->buildFileAttachment($item);
            if ($built !== null) {
                $graphAttachments[] = $built;
            }
        }

        if ($inlineLogoPath !== null && $inlineLogoPath !== '' && is_file($inlineLogoPath)) {
            $logoContent = @file_get_contents($inlineLogoPath);
            if ($logoContent !== false) {
                $graphAttachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => basename($inlineLogoPath),
                    'contentType' => 'image/png',
                    'contentId' => $inlineLogoCid ?: 'sitandgo-logo',
                    'isInline' => true,
                    'contentBytes' => base64_encode($logoContent),
                ];
            }
        }

        if ($graphAttachments !== []) {
            $message['attachments'] = $graphAttachments;
        }

        $sendResponse = Http::withToken($accessToken)
            ->timeout(60)
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($fromEmail).'/sendMail', [
                'message' => $message,
                'saveToSentItems' => true,
            ]);

        if (! $sendResponse->successful()) {
            throw new RuntimeException('Microsoft Graph send failed: '.$sendResponse->body());
        }
    }

    /**
     * @param  string|array{path:string,name?:string,content_type?:string}  $item
     * @return array<string, mixed>|null
     */
    private function buildFileAttachment(string|array $item): ?array
    {
        if (is_string($item)) {
            $item = ['path' => $item];
        }

        $path = trim((string) ($item['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $disk = Storage::disk('local');
        $contents = null;
        $name = trim((string) ($item['name'] ?? ''));
        $contentType = trim((string) ($item['content_type'] ?? ''));

        if (is_file($path)) {
            $contents = @file_get_contents($path);
            if ($name === '') {
                $name = basename($path);
            }
            if ($contentType === '') {
                $contentType = mime_content_type($path) ?: 'application/octet-stream';
            }
        } elseif ($disk->exists($path)) {
            $contents = $disk->get($path);
            if ($name === '') {
                $name = basename(str_replace('\\', '/', $path));
            }
            if ($contentType === '') {
                $contentType = $disk->mimeType($path) ?: 'application/octet-stream';
            }
        } else {
            throw new RuntimeException("Attachment file not found: {$path}");
        }

        if ($contents === false || $contents === null || $contents === '') {
            throw new RuntimeException("Attachment file is empty: {$path}");
        }

        return [
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'name' => $name,
            'contentType' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'contentBytes' => base64_encode($contents),
        ];
    }
}
