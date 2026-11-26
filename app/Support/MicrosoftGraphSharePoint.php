<?php

namespace App\Support;

use App\Models\EmailSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

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
            ->timeout(HttpTimeouts::default())
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
        $relativePath = $folderPath !== '' ? $folderPath.'/'.$fileName : $fileName;

        try {
            if ($fileName === '' || str_contains($fileName, '/')) {
                throw new RuntimeException('Upload file name must be a plain file name (no folders).');
            }

            $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));

            $siteId = $this->resolveSiteId($siteUrl);
            $url = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId)
                .'/drive/root:/'.$encodedPath.':/content';

            $response = Http::withToken($this->accessToken())
                ->timeout(HttpTimeouts::long())
                ->withBody($contents, 'application/octet-stream')
                ->put($url);

            if (! $response->successful()) {
                throw new RuntimeException('SharePoint upload failed: '.$response->body());
            }

            $result = [
                'id' => (string) ($response->json('id') ?? ''),
                'name' => (string) ($response->json('name') ?? $fileName),
                'webUrl' => (string) ($response->json('webUrl') ?? ''),
                'size' => (int) ($response->json('size') ?? strlen($contents)),
            ];

            $this->logIo('write', [
                'file' => $fileName,
                'folder' => $folderPath,
                'bytes' => strlen($contents),
                'web_url' => $result['webUrl'] !== '' ? $result['webUrl'] : null,
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logIo('write', [
                'file' => $fileName,
                'folder' => $folderPath,
                'bytes' => strlen($contents),
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
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

        try {
            if ($fileName === '' || str_contains($fileName, '/')) {
                throw new RuntimeException('Download file name must be a plain file name (no folders).');
            }

            $relativePath = $folderPath !== '' ? $folderPath.'/'.$fileName : $fileName;
            $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));

            $siteId = $this->resolveSiteId($siteUrl);
            $url = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId)
                .'/drive/root:/'.$encodedPath.':/content';

            $response = Http::withToken($this->accessToken())
                ->timeout(HttpTimeouts::long())
                ->withHeaders(['Accept' => 'application/octet-stream'])
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('SharePoint download failed: '.$response->body());
            }

            $body = (string) $response->body();
            $this->logIo('read', [
                'file' => $fileName,
                'folder' => $folderPath,
                'bytes' => strlen($body),
            ]);

            return $body;
        } catch (Throwable $e) {
            $this->logIo('read', [
                'file' => $fileName,
                'folder' => $folderPath,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * Ensure a folder path exists under the site drive (creates missing segments).
     *
     * @return array{id:string,name:string,webUrl:string}
     */
    public function ensureFolderExists(?string $folderPath = null, ?string $siteUrl = null): array
    {
        $config = EmailSetting::sharePointConfig();
        $folderPath = trim(str_replace('\\', '/', (string) ($folderPath ?? $config['folder_path'])), '/');

        try {
            if ($folderPath === '') {
                throw new RuntimeException('SharePoint folder path is empty.');
            }

            $siteId = $this->resolveSiteId($siteUrl);
            $token = $this->accessToken();
            $segments = array_values(array_filter(
                explode('/', $folderPath),
                static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'
            ));

            $built = '';
            $created = [];
            $last = ['id' => '', 'name' => '', 'webUrl' => ''];

            foreach ($segments as $segment) {
                $built = $built === '' ? $segment : $built.'/'.$segment;
                $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', $built)));
                $getUrl = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId).'/drive/root:/'.$encodedPath;

                $response = Http::withToken($token)->timeout(HttpTimeouts::default())->get($getUrl);
                if ($response->successful()) {
                    $last = [
                        'id' => (string) ($response->json('id') ?? ''),
                        'name' => (string) ($response->json('name') ?? $segment),
                        'webUrl' => (string) ($response->json('webUrl') ?? ''),
                    ];

                    continue;
                }

                $parentPath = trim(dirname(str_replace('\\', '/', $built)), '/.');
                if ($parentPath === '' || $parentPath === '.') {
                    $createUrl = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId).'/drive/root/children';
                } else {
                    $parentEncoded = implode('/', array_map(rawurlencode(...), explode('/', $parentPath)));
                    $createUrl = 'https://graph.microsoft.com/v1.0/sites/'.rawurlencode($siteId)
                        .'/drive/root:/'.$parentEncoded.':/children';
                }

                $create = Http::withToken($token)
                    ->timeout(HttpTimeouts::default())
                    ->post($createUrl, [
                        'name' => $segment,
                        'folder' => (object) [],
                        '@microsoft.graph.conflictBehavior' => 'fail',
                    ]);

                if (! $create->successful()) {
                    // Race / already created → re-fetch.
                    $retry = Http::withToken($token)->timeout(HttpTimeouts::default())->get($getUrl);
                    if ($retry->successful()) {
                        $last = [
                            'id' => (string) ($retry->json('id') ?? ''),
                            'name' => (string) ($retry->json('name') ?? $segment),
                            'webUrl' => (string) ($retry->json('webUrl') ?? ''),
                        ];

                        continue;
                    }

                    throw new RuntimeException(
                        'SharePoint folder create failed for '.$built.': '.$create->body()
                    );
                }

                $last = [
                    'id' => (string) ($create->json('id') ?? ''),
                    'name' => (string) ($create->json('name') ?? $segment),
                    'webUrl' => (string) ($create->json('webUrl') ?? ''),
                ];
                $created[] = $built;
            }

            $this->logIo('ensure_folder', [
                'folder' => $folderPath,
                'created_segments' => $created,
                'web_url' => $last['webUrl'] !== '' ? $last['webUrl'] : null,
            ]);

            return $last;
        } catch (Throwable $e) {
            $this->logIo('ensure_folder', [
                'folder' => $folderPath,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logIo(string $op, array $meta = [], bool $ok = true): void
    {
        try {
            ActivityLogger::log(
                ($ok ? 'SharePoint: ' : 'SharePoint failed: ').$op,
                meta: array_merge(['ok' => $ok, 'system' => 'sharepoint'], $meta),
            );
        } catch (Throwable) {
            // Never break SharePoint IO because of logging.
        }
    }
}
