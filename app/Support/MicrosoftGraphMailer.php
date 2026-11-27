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

        // Graph /users/{id}/sendMail must use a real mailbox in the tenant.
        // System "From" is often a public address (.com) while the mailbox is .lt.
        $mailbox = trim((string) ($config['inbox_mailbox'] ?? ''));
        if ($mailbox === '') {
            $mailbox = $fromEmail;
        }
        $mailbox = $this->preferValidGreenleaseMailbox($mailbox, $fromEmail);
        $fromHeader = $this->preferValidGreenleaseMailbox($fromEmail, $mailbox);

        $tokenResponse = Http::asForm()
            ->timeout(HttpTimeouts::short())
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

        if (strcasecmp($fromHeader, $mailbox) !== 0) {
            $message['from'] = [
                'emailAddress' => [
                    'address' => $fromHeader,
                ],
            ];
        }

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
            ->timeout(HttpTimeouts::long())
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox).'/sendMail', [
                'message' => $message,
                'saveToSentItems' => true,
            ]);

        if (! $sendResponse->successful()) {
            $body = $sendResponse->body();
            if (str_contains($body, 'ErrorInvalidUser')) {
                throw new RuntimeException(
                    'Microsoft Graph send failed: mailbox "'.$mailbox.'" is invalid in the tenant. '
                    .'Set System settings → Inbox mailbox to the real M365 address (e.g. administration@greenlease.lt), '
                    .'not a public From alias. Graph response: '.$body
                );
            }
            throw new RuntimeException('Microsoft Graph send failed: '.$body);
        }
    }

    /**
     * Prefer greenlease.lt mailbox when .com twin is used (Graph rejects unknown .com user).
     */
    private function preferValidGreenleaseMailbox(string $preferred, string $fallback): string
    {
        $preferred = trim($preferred);
        $fallback = trim($fallback);
        if ($preferred === '') {
            return $fallback;
        }

        if (preg_match('/^(administration)@greenlease\.com$/i', $preferred, $m)) {
            $lt = $m[1].'@greenlease.lt';
            if ($fallback !== '' && strcasecmp($fallback, $lt) === 0) {
                return $lt;
            }

            return $lt;
        }

        return $preferred;
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
