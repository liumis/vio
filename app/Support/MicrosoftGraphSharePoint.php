<?php

namespace App\Support;

use App\Models\EmailSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MicrosoftGraphSharePoint
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

    public function resolveSiteId(?string $siteUrl = null): string
    {
        $siteUrl = trim((string) ($siteUrl ?: EmailSetting::sharePointConfig()['site_url']));
        if ($siteUrl === '') {
            throw new RuntimeException('SharePoint site URL is empty. Set it in System settings.');
        }

        // Accept both https://host/sites/Name and https://host:/sites/Name
        $normalized = str_replace(':/sites/', '/sites/', $siteUrl);
        $parsed = parse_url($normalized);
        $host = $parsed['host'] ?? null;
        $path = $parsed['path'] ?? null;

        if (! $host || ! $path) {
            throw new RuntimeException('Invalid SharePoint site URL: '.$siteUrl);
        }

        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->get('https://graph.microsoft.com/v1.0/sites/'.$host.':'.$path);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to resolve SharePoint site: '.$response->body());
        }

        $siteId = (string) ($response->json('id') ?? '');
        if ($siteId === '') {
            throw new RuntimeException('SharePoint site response did not include id.');
        }

        return $siteId;
    }

    /**
     * Upload a file into the configured folder (or an absolute folder under the site drive).
     *
     * @return array{id:string,name:string,webUrl:string,size:int}
     */
    public function upload(
        string $fileName,
        string $contents,
        ?string $folderPath = null,
        ?string $siteUrl = null,
    ): array {
        $config = EmailSetting::sharePointConfig();
        $folderPath = trim(str_replace('\\', '/', (string) ($folderPath ?? $config['folder_path'])), '/');
        $fileName = ltrim(str_replace('\\', '/', $fileName), '/');

        if ($fileName === '' || str_contains($fileName, '/')) {
            throw new RuntimeException('Upload file name must be a plain file name (no folders).');
        }

        $relativePath = $folderPath !== '' ? $folderPath.'/'.$fileName : $fileName;
        $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));

        $siteId = $this->resolveSiteId($siteUrl);
        $url = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId)
            .'/drive/root:/'.$encodedPath.':/content';

        $response = Http::withToken($this->accessToken())
            ->timeout(60)
            ->withBody($contents, 'application/octet-stream')
            ->put($url);

        if (! $response->successful()) {
            throw new RuntimeException('SharePoint upload failed: '.$response->body());
        }

        return [
            'id' => (string) ($response->json('id') ?? ''),
            'name' => (string) ($response->json('name') ?? $fileName),
            'webUrl' => (string) ($response->json('webUrl') ?? ''),
            'size' => (int) ($response->json('size') ?? strlen($contents)),
        ];
    }

    /**
     * Download a file from the configured SharePoint folder (or an absolute folder under the site drive).
     */
    public function download(
        string $fileName,
        ?string $folderPath = null,
        ?string $siteUrl = null,
    ): string {
        $config = EmailSetting::sharePointConfig();
        $folderPath = trim(str_replace('\\', '/', (string) ($folderPath ?? $config['folder_path'])), '/');
        $fileName = ltrim(str_replace('\\', '/', $fileName), '/');

        if ($fileName === '' || str_contains($fileName, '/')) {
            throw new RuntimeException('Download file name must be a plain file name (no folders).');
        }

        $relativePath = $folderPath !== '' ? $folderPath.'/'.$fileName : $fileName;
        $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));

        $siteId = $this->resolveSiteId($siteUrl);
        $url = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId)
            .'/drive/root:/'.$encodedPath.':/content';

        $response = Http::withToken($this->accessToken())
            ->timeout(90)
            ->withHeaders(['Accept' => 'application/octet-stream'])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('SharePoint download failed: '.$response->body());
        }

        return (string) $response->body();
    }

    /**
     * @return array{id:string,name:string,webUrl:string}
     */
    public function ensureFolderExists(?string $folderPath = null, ?string $siteUrl = null): array
    {
        $config = EmailSetting::sharePointConfig();
        $folderPath = trim(str_replace('\\', '/', (string) ($folderPath ?? $config['folder_path'])), '/');

        if ($folderPath === '') {
            throw new RuntimeException('SharePoint folder path is empty.');
        }

        $siteId = $this->resolveSiteId($siteUrl);
        $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $folderPath)));

        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->get('https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId).'/drive/root:/'.$encodedPath);

        if ($response->successful()) {
            return [
                'id' => (string) ($response->json('id') ?? ''),
                'name' => (string) ($response->json('name') ?? ''),
                'webUrl' => (string) ($response->json('webUrl') ?? ''),
            ];
        }

        throw new RuntimeException('SharePoint folder not found (create it in SharePoint first): '.$folderPath.' — '.$response->body());
    }
}
