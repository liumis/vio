<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MicrosoftGraphMailer
{
    public function sendHtml(
        string $fromEmail,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $replyTo = null,
        ?string $attachmentPath = null,
        ?string $inlineLogoPath = null,
        ?string $inlineLogoCid = null
    ): void
    {
        $tenantId = trim((string) config('services.microsoft_o365.tenant_id'));
        $clientId = trim((string) config('services.microsoft_o365.client_id'));
        $clientSecret = trim((string) config('services.microsoft_o365.client_secret'));

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Microsoft 365 OAuth is not configured. Set O365_TENANT_ID, O365_CLIENT_ID, and O365_CLIENT_SECRET.');
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

        $attachments = [];

        if ($attachmentPath !== null && $attachmentPath !== '') {
            $disk = Storage::disk('local');

            if (! $disk->exists($attachmentPath)) {
                throw new RuntimeException("Attachment file not found: {$attachmentPath}");
            }

            $attachmentContent = $disk->get($attachmentPath);
            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => basename($attachmentPath),
                'contentType' => $disk->mimeType($attachmentPath) ?: 'application/octet-stream',
                'contentBytes' => base64_encode($attachmentContent),
            ];
        }

        if ($inlineLogoPath !== null && $inlineLogoPath !== '' && is_file($inlineLogoPath)) {
            $logoContent = @file_get_contents($inlineLogoPath);
            if ($logoContent !== false) {
                $attachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => basename($inlineLogoPath),
                    'contentType' => 'image/png',
                    'contentId' => $inlineLogoCid ?: 'sitandgo-logo',
                    'isInline' => true,
                    'contentBytes' => base64_encode($logoContent),
                ];
            }
        }

        if ($attachments !== []) {
            $message['attachments'] = $attachments;
        }

        $sendResponse = Http::withToken($accessToken)
            ->timeout(20)
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($fromEmail).'/sendMail', [
                'message' => $message,
                'saveToSentItems' => true,
            ]);

        if (! $sendResponse->successful()) {
            throw new RuntimeException('Microsoft Graph send failed: '.$sendResponse->body());
        }
    }
}
