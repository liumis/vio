<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Ensures O365 mailbox folders and moves inbound fine messages:
 * - "VIO pending" when included in a Wheelsys import Excel (or marked created in Wheelsys)
 * - "VIO Processed" when the authority reply is sent (or ePaslaugos marked done)
 */
final class IncomingFineMailboxFolders
{
    public const PENDING = 'VIO pending';

    /** Exact mailbox display name (capital P). */
    public const PROCESSED = 'VIO Processed';

    /**
     * Alternate spellings already used in DB / older code — matched case-insensitively.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        self::PENDING => ['VIO pending'],
        self::PROCESSED => ['VIO Processed', 'VIO processed'],
    ];

    /** @var array<string, array{id: string, displayName: string}> cacheKey => folder */
    private array $folderCache = [];

    public function __construct(
        private readonly MicrosoftGraphInbox $graph = new MicrosoftGraphInbox,
    ) {
    }

    public function moveToPending(FineProcessLog $log): bool
    {
        return $this->moveLog($log, self::PENDING, movedToProcessed: false);
    }

    public function moveToProcessed(FineProcessLog $log): bool
    {
        return $this->moveLog($log, self::PROCESSED, movedToProcessed: true);
    }

    /**
     * @return array{id: string, displayName: string}
     */
    public function ensureFolder(string $displayName, ?string $mailbox = null): array
    {
        $displayName = trim($displayName);
        if ($displayName === '') {
            throw new RuntimeException('Folder display name is empty.');
        }

        $mailbox = trim((string) ($mailbox ?: $this->graph->mailbox()));
        $cacheKey = strtolower($mailbox).'|'.mb_strtolower($displayName);
        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        $token = $this->graph->accessToken();
        $aliases = self::ALIASES[$displayName] ?? [$displayName];
        $existing = $this->findFolderByNames($mailbox, $aliases, $token);
        if ($existing !== null) {
            $this->folderCache[$cacheKey] = $existing;

            return $existing;
        }

        $response = Http::withToken($token)
            ->timeout(HttpTimeouts::default())
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox).'/mailFolders',
                ['displayName' => $displayName]
            );

        if (! $response->successful()) {
            // Race: folder created between find and create.
            $existing = $this->findFolderByNames($mailbox, $aliases, $token);
            if ($existing !== null) {
                $this->folderCache[$cacheKey] = $existing;

                return $existing;
            }

            throw new RuntimeException('Failed to create mail folder "'.$displayName.'": '.$response->body());
        }

        $id = trim((string) ($response->json('id') ?? ''));
        $createdName = trim((string) ($response->json('displayName') ?? $displayName));
        if ($id === '') {
            throw new RuntimeException('Mail folder "'.$displayName.'" created but id was empty.');
        }

        $folder = ['id' => $id, 'displayName' => $createdName !== '' ? $createdName : $displayName];
        $this->folderCache[$cacheKey] = $folder;

        return $folder;
    }

    public function ensureStandardFolders(?string $mailbox = null): void
    {
        $this->ensureFolder(self::PENDING, $mailbox);
        $this->ensureFolder(self::PROCESSED, $mailbox);
    }

    private function moveLog(FineProcessLog $log, string $folderName, bool $movedToProcessed): bool
    {
        $messageId = trim((string) ($log->graph_message_id ?? ''));
        if (! $this->isMovableGraphMessageId($messageId)) {
            return false;
        }

        $current = trim((string) ($log->processed_folder ?? ''));
        if ($this->alreadyInTarget($current, (bool) $log->moved_to_processed, $folderName, $movedToProcessed)) {
            return true;
        }

        // Never move a finished mail back to pending.
        if (! $movedToProcessed && $this->isProcessedFolderName($current) && (bool) $log->moved_to_processed) {
            return true;
        }

        try {
            $mailbox = trim((string) ($log->mailbox ?: $this->graph->mailbox()));
            $folder = $this->ensureFolder($folderName, $mailbox);
            $moved = $this->moveMessage($messageId, $folder['id'], $mailbox);
            $newId = trim((string) ($moved['id'] ?? ''));

            $log->refresh();
            $workflow = is_array($log->workflow) ? $log->workflow : [];
            $workflow['mailbox_folder'] = [
                'name' => $folder['displayName'],
                'moved_at' => now()->toIso8601String(),
                'previous_message_id' => $messageId,
                'message_id' => $newId !== '' ? $newId : $messageId,
            ];
            unset($workflow['mailbox_folder_error']);

            $fill = [
                'processed_folder' => $folder['displayName'],
                'moved_to_processed' => $movedToProcessed,
                'workflow' => $workflow,
            ];
            if ($newId !== '' && $newId !== $messageId) {
                $fill['graph_message_id'] = $newId;
            }

            $log->fill($fill)->save();

            ActivityLogger::log(
                'Mailbox → '.$folder['displayName'].($log->reference ? ' · '.$log->reference : ''),
                meta: [
                    'fine_process_log_id' => $log->id,
                    'folder' => $folder['displayName'],
                    'moved_to_processed' => $movedToProcessed,
                ]
            );

            return true;
        } catch (Throwable $e) {
            try {
                $log->refresh();
                $workflow = is_array($log->workflow) ? $log->workflow : [];
                $workflow['mailbox_folder_error'] = [
                    'folder' => $folderName,
                    'at' => now()->toIso8601String(),
                    'error' => $e->getMessage(),
                ];
                $log->fill(['workflow' => $workflow])->save();
            } catch (Throwable) {
                // ignore secondary persistence errors
            }

            ActivityLogger::log(
                'Mailbox folder move failed · '.$folderName.($log->reference ? ' · '.$log->reference : ''),
                meta: [
                    'fine_process_log_id' => $log->id,
                    'folder' => $folderName,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    private function alreadyInTarget(
        string $currentFolder,
        bool $currentlyProcessed,
        string $targetFolder,
        bool $targetProcessed,
    ): bool {
        if ($currentlyProcessed !== $targetProcessed) {
            return false;
        }

        if ($currentFolder === '') {
            return false;
        }

        return $this->namesMatch($currentFolder, $targetFolder)
            || ($targetProcessed && $this->isProcessedFolderName($currentFolder))
            || (! $targetProcessed && $this->isPendingFolderName($currentFolder));
    }

    private function isPendingFolderName(string $name): bool
    {
        return $this->namesMatch($name, self::PENDING);
    }

    private function isProcessedFolderName(string $name): bool
    {
        foreach (self::ALIASES[self::PROCESSED] as $alias) {
            if ($this->namesMatch($name, $alias)) {
                return true;
            }
        }

        return false;
    }

    private function namesMatch(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    /**
     * @return array<string, mixed>
     */
    private function moveMessage(string $messageId, string $destinationFolderId, string $mailbox): array
    {
        $response = Http::withToken($this->graph->accessToken())
            ->timeout(HttpTimeouts::default())
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
                .'/messages/'.rawurlencode($messageId).'/move',
                ['destinationId' => $destinationFolderId]
            );

        if (! $response->successful()) {
            throw new RuntimeException('Graph move failed: '.$response->body());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  list<string>  $displayNames
     * @return array{id: string, displayName: string}|null
     */
    private function findFolderByNames(string $mailbox, array $displayNames, string $token): ?array
    {
        $wanted = [];
        foreach ($displayNames as $name) {
            $key = mb_strtolower(trim($name));
            if ($key !== '') {
                $wanted[$key] = true;
            }
        }
        if ($wanted === []) {
            return null;
        }

        $rootUrl = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
            .'/mailFolders?$select=id,displayName,childFolderCount&$top=100';

        return $this->scanFoldersRecursive($rootUrl, $token, $mailbox, $wanted, depth: 0);
    }

    /**
     * @param  array<string, true>  $wantedLower
     * @return array{id: string, displayName: string}|null
     */
    private function scanFoldersRecursive(
        string $url,
        string $token,
        string $mailbox,
        array $wantedLower,
        int $depth,
    ): ?array {
        if ($depth > 5) {
            return null;
        }

        $guards = 0;
        while ($url !== '' && $guards < 20) {
            $guards++;
            $response = Http::withToken($token)->timeout(HttpTimeouts::default())->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('Failed to list mail folders: '.$response->body());
            }

            foreach (($response->json('value') ?? []) as $folder) {
                if (! is_array($folder)) {
                    continue;
                }
                $name = trim((string) ($folder['displayName'] ?? ''));
                $id = trim((string) ($folder['id'] ?? ''));
                if ($id !== '' && isset($wantedLower[mb_strtolower($name)])) {
                    return ['id' => $id, 'displayName' => $name];
                }

                $childCount = (int) ($folder['childFolderCount'] ?? 0);
                if ($childCount > 0 && $id !== '') {
                    $childUrl = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
                        .'/mailFolders/'.rawurlencode($id)
                        .'/childFolders?$select=id,displayName,childFolderCount&$top=100';
                    $found = $this->scanFoldersRecursive($childUrl, $token, $mailbox, $wantedLower, $depth + 1);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            $url = trim((string) ($response->json('@odata.nextLink') ?? ''));
        }

        return null;
    }

    private function isMovableGraphMessageId(string $messageId): bool
    {
        if ($messageId === '') {
            return false;
        }

        // Synthetic ids from physical mail / court / manual send — not in O365 inbox.
        if (preg_match('/^(physical|court|manual|manual-send|vio-test)-/i', $messageId)) {
            return false;
        }

        return true;
    }
}
