<?php

namespace App\Support;

use App\Models\EmailSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MicrosoftGraphInbox
{
    public function accessToken(): string
    {
        $config = EmailSetting::graphConfig();
        $tenantId = $config['tenant_id'];
        $clientId = $config['client_id'];
        $clientSecret = $config['client_secret'];

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Microsoft 365 OAuth is not configured in System settings.');
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

        return $accessToken;
    }

    /**
     * @return list<array{
     *     id: string,
     *     subject: string,
     *     from_email: string,
     *     from_name: string,
     *     received_at: string,
     *     preview: string,
     *     has_attachments: bool
     * }>
     */
    public function listInboxMessages(?string $mailbox = null, int $days = 3, int $top = 100): array
    {
        return $this->listFolderMessages('inbox', $mailbox, $days, $top);
    }

    /**
     * Inbox-only fine mailbox listing (VIO tracks handled state in DB — no folder moves).
     *
     * @return list<array{
     *     id: string,
     *     subject: string,
     *     from_email: string,
     *     from_name: string,
     *     received_at: string,
     *     preview: string,
     *     has_attachments: bool,
     *     folder?: string
     * }>
     */
    public function listFineMailboxMessages(?string $mailbox = null, int $days = 3, int $top = 100): array
    {
        $inbox = $this->listInboxMessages($mailbox, $days, $top);
        foreach ($inbox as &$row) {
            $row['folder'] = 'inbox';
        }
        unset($row);

        return $inbox;
    }

    /**
     * @return list<array{
     *     id: string,
     *     subject: string,
     *     from_email: string,
     *     from_name: string,
     *     received_at: string,
     *     preview: string,
     *     has_attachments: bool
     * }>
     */
    public function listFolderMessages(string $folderIdOrWellKnown, ?string $mailbox = null, int $days = 3, int $top = 100): array
    {
        $config = EmailSetting::graphConfig();
        $mailbox = trim((string) ($mailbox ?: $config['inbox_mailbox'] ?: $config['from_email']));

        if ($mailbox === '') {
            throw new RuntimeException('Inbox mailbox is empty. Set it in System settings.');
        }

        $since = now()->subDays(max(1, $days))->utc()->format('Y-m-d\TH:i:s\Z');
        $pageSize = max(1, min(100, $top > 0 ? min($top, 100) : 100));
        // Cap how many messages we pull for long look-backs (pagination via @odata.nextLink).
        $maxMessages = max($pageSize, min(2000, max(1, $top)));

        $folderSegment = rawurlencode($folderIdOrWellKnown);
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox).'/mailFolders/'.$folderSegment.'/messages'
            .'?$filter='.rawurlencode("receivedDateTime ge {$since}")
            .'&$select='.rawurlencode('id,subject,from,receivedDateTime,bodyPreview,hasAttachments')
            .'&$orderby='.rawurlencode('receivedDateTime desc')
            .'&$top='.$pageSize;

        $token = $this->accessToken();
        $out = [];
        $guards = 0;

        while ($url !== '' && count($out) < $maxMessages && $guards < 40) {
            $guards++;
            $response = Http::withToken($token)
                ->timeout(60)
                ->withHeaders([
                    'Prefer' => 'outlook.body-content-type="text"',
                ])
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('Microsoft Graph inbox read failed: '.$response->body());
            }

            $items = $response->json('value') ?? [];
            if (! is_array($items)) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $from = is_array($item['from'] ?? null) ? $item['from'] : [];
                $emailAddress = is_array($from['emailAddress'] ?? null) ? $from['emailAddress'] : [];

                $out[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'subject' => trim((string) ($item['subject'] ?? '')),
                    'from_email' => trim((string) ($emailAddress['address'] ?? '')),
                    'from_name' => trim((string) ($emailAddress['name'] ?? '')),
                    'received_at' => (string) ($item['receivedDateTime'] ?? ''),
                    'preview' => trim((string) ($item['bodyPreview'] ?? '')),
                    'has_attachments' => (bool) ($item['hasAttachments'] ?? false),
                ];

                if (count($out) >= $maxMessages) {
                    break;
                }
            }

            $next = trim((string) ($response->json('@odata.nextLink') ?? ''));
            $url = $next !== '' && count($out) < $maxMessages ? $next : '';
        }

        return $out;
    }

    /**
     * Graph inbox $filter often stops at ~100 without nextLink. Supplement with $search by Authority domains.
     *
     * @param  list<string>  $domains  bare domains, e.g. ["unipark.lt","julianus.lt"]
     * @return list<array{
     *     id: string,
     *     subject: string,
     *     from_email: string,
     *     from_name: string,
     *     received_at: string,
     *     preview: string,
     *     has_attachments: bool,
     *     folder?: string
     * }>
     */
    public function searchMessagesFromDomains(array $domains, int $days = 90, int $perDomain = 25, ?string $mailbox = null): array
    {
        $mailbox = trim((string) ($mailbox ?: $this->mailbox()));
        $token = $this->accessToken();
        $sinceTs = now()->subDays(max(1, $days))->getTimestamp();
        $out = [];
        $seen = [];

        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            $domain = ltrim($domain, '@');
            if ($domain === '') {
                continue;
            }

            $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox).'/messages'
                .'?$search='.rawurlencode('"from:'.$domain.'"')
                .'&$select='.rawurlencode('id,subject,from,receivedDateTime,bodyPreview,hasAttachments')
                .'&$top='.max(1, min(50, $perDomain));

            $response = Http::withToken($token)
                ->timeout(60)
                ->withHeaders(['ConsistencyLevel' => 'eventual'])
                ->get($url);

            if (! $response->successful()) {
                continue;
            }

            foreach (($response->json('value') ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? '');
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }

                $receivedAt = (string) ($item['receivedDateTime'] ?? '');
                if ($receivedAt !== '') {
                    try {
                        if (\Illuminate\Support\Carbon::parse($receivedAt)->getTimestamp() < $sinceTs) {
                            continue;
                        }
                    } catch (\Throwable) {
                        // keep if unparsable
                    }
                }

                $from = is_array($item['from'] ?? null) ? $item['from'] : [];
                $emailAddress = is_array($from['emailAddress'] ?? null) ? $from['emailAddress'] : [];
                $fromEmail = trim((string) ($emailAddress['address'] ?? ''));
                if ($fromEmail === '' || ! str_ends_with(strtolower($fromEmail), '@'.$domain)) {
                    continue;
                }

                $seen[$id] = true;
                $out[] = [
                    'id' => $id,
                    'subject' => trim((string) ($item['subject'] ?? '')),
                    'from_email' => $fromEmail,
                    'from_name' => trim((string) ($emailAddress['name'] ?? '')),
                    'received_at' => $receivedAt,
                    'preview' => trim((string) ($item['bodyPreview'] ?? '')),
                    'has_attachments' => (bool) ($item['hasAttachments'] ?? false),
                    'folder' => 'search',
                ];
            }
        }

        return $out;
    }

    /**
     * Plain-text body for messages without usable PDF attachments (Unipark HTML mails, etc.).
     */
    public function getMessageBodyText(string $messageId, ?string $mailbox = null): string
    {
        $mailbox = trim((string) ($mailbox ?: $this->mailbox()));
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
            .'/messages/'.rawurlencode($messageId)
            .'?$select=body,bodyPreview';

        $response = Http::withToken($this->accessToken())
            ->timeout(40)
            ->withHeaders([
                'Prefer' => 'outlook.body-content-type="text"',
            ])
            ->get($url);

        if (! $response->successful()) {
            return '';
        }

        $content = trim((string) data_get($response->json(), 'body.content', ''));
        if ($content === '') {
            $content = trim((string) ($response->json('bodyPreview') ?? ''));
        }

        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace("/[ \t]+/u", ' ', $content) ?? $content;
        $content = preg_replace("/\n{3,}/u", "\n\n", $content) ?? $content;

        return trim($content);
    }

    /**
     * HTML body for Print-to-PDF of letter-form notices (JUDU / Unipark / Parkavimas mieste).
     */
    public function getMessageBodyHtml(string $messageId, ?string $mailbox = null): string
    {
        $mailbox = trim((string) ($mailbox ?: $this->mailbox()));
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
            .'/messages/'.rawurlencode($messageId)
            .'?$select=body,bodyPreview';

        $response = Http::withToken($this->accessToken())
            ->timeout(40)
            ->withHeaders([
                'Prefer' => 'outlook.body-content-type="html"',
            ])
            ->get($url);

        if (! $response->successful()) {
            return '';
        }

        $content = trim((string) data_get($response->json(), 'body.content', ''));
        if ($content === '') {
            $preview = trim((string) ($response->json('bodyPreview') ?? ''));

            return $preview !== '' ? nl2br(e($preview)) : '';
        }

        return $content;
    }

    public function mailbox(): string
    {
        $config = EmailSetting::graphConfig();
        $mailbox = trim((string) ($config['inbox_mailbox'] ?: $config['from_email']));

        if ($mailbox === '') {
            throw new RuntimeException('Inbox mailbox is empty. Set it in System settings.');
        }

        return $mailbox;
    }

    /**
     * @return list<array{id:string,name:string,contentType:string,size:int,contentBytes:?string,isInline:bool}>
     */
    public function listFileAttachments(string $messageId, ?string $mailbox = null): array
    {
        $mailbox = trim((string) ($mailbox ?: $this->mailbox()));
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
            .'/messages/'.rawurlencode($messageId)
            .'/attachments?$select=id,name,contentType,size,isInline';

        $response = Http::withToken($this->accessToken())
            ->timeout(40)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to list attachments: '.$response->body());
        }

        $items = $response->json('value') ?? [];
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['@odata.type'] ?? '');
            if (! str_contains($type, 'fileAttachment')) {
                continue;
            }

            if (! empty($item['isInline'])) {
                continue;
            }

            $out[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? 'attachment.bin'),
                'contentType' => (string) ($item['contentType'] ?? 'application/octet-stream'),
                'size' => (int) ($item['size'] ?? 0),
                'contentBytes' => null,
                'isInline' => false,
            ];
        }

        return $out;
    }

    public function downloadAttachmentContent(string $messageId, string $attachmentId, ?string $mailbox = null): string
    {
        $mailbox = trim((string) ($mailbox ?: $this->mailbox()));
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
            .'/messages/'.rawurlencode($messageId)
            .'/attachments/'.rawurlencode($attachmentId);

        $response = Http::withToken($this->accessToken())
            ->timeout(60)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download attachment: '.$response->body());
        }

        $bytes = (string) ($response->json('contentBytes') ?? '');
        if ($bytes === '') {
            throw new RuntimeException('Attachment contentBytes was empty.');
        }

        $decoded = base64_decode($bytes, true);
        if ($decoded === false) {
            throw new RuntimeException('Attachment contentBytes was not valid base64.');
        }

        return $decoded;
    }
}
