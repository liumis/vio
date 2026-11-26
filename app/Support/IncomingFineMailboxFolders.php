<?php

namespace App\Support;

use App\Models\FineProcessLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Ensures O365 mailbox folders and moves inbound fine messages:
 * - "VIO pending" when included in a Wheelsys import Excel
 * - "VIO processed" when the authority reply is sent (or ePaslaugos marked done)
 */
final class IncomingFineMailboxFolders
{
    public const PENDING = 'VIO pending';

    public const PROCESSED = 'VIO processed';

    /** @var array<string, string> displayName => folder id (per request) */
    private array $folderIdCache = [];

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
        if (isset($this->folderIdCache[$cacheKey])) {
            return [
                'id' => $this->folderIdCache[$cacheKey],
                'displayName' => $displayName,
            ];
        }

        $token = $this->graph->accessToken();
        $existing = $this->findFolderIdByName($mailbox, $displayName, $token);
        if ($existing !== null) {
            $this->folderIdCache[$cacheKey] = $existing;

            return ['id' => $existing, 'displayName' => $displayName];
        }

        $response = Http::withToken($token)
            ->timeout(HttpTimeouts::default())
            ->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox).'/mailFolders',
                ['displayName' => $displayName]
            );

        if (! $response->successful()) {
            // Race: folder created between find and create.
            $existing = $this->findFolderIdByName($mailbox, $displayName, $token);
            if ($existing !== null) {
                $this->folderIdCache[$cacheKey] = $existing;

                return ['id' => $existing, 'displayName' => $displayName];
            }

            throw new RuntimeException('Failed to create mail folder "'.$displayName.'": '.$response->body());
        }

        $id = trim((string) ($response->json('id') ?? ''));
        if ($id === '') {
            throw new RuntimeException('Mail folder "'.$displayName.'" created but id was empty.');
        }

        $this->folderIdCache[$cacheKey] = $id;

        return ['id' => $id, 'displayName' => $displayName];
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

        if (strcasecmp((string) ($log->processed_folder ?? ''), $folderName) === 0
            && (bool) $log->moved_to_processed === $movedToProcessed) {
            return true;
        }

        try {
            $mailbox = trim((string) ($log->mailbox ?: $this->graph->mailbox()));
            $folder = $this->ensureFolder($folderName, $mailbox);
            $moved = $this->moveMessage($messageId, $folder['id'], $mailbox);
            $newId = trim((string) ($moved['id'] ?? ''));

            $fill = [
                'processed_folder' => $folderName,
                'moved_to_processed' => $movedToProcessed,
            ];
            if ($newId !== '' && $newId !== $messageId) {
                $fill['graph_message_id'] = $newId;
            }

            $workflow = is_array($log->workflow) ? $log->workflow : [];
            $workflow['mailbox_folder'] = [
                'name' => $folderName,
                'moved_at' => now()->toIso8601String(),
                'previous_message_id' => $messageId,
                'message_id' => $newId !== '' ? $newId : $messageId,
            ];
            $fill['workflow'] = $workflow;

            $log->fill($fill)->save();

            return true;
        } catch (Throwable $e) {
            $workflow = is_array($log->workflow) ? $log->workflow : [];
            $workflow['mailbox_folder_error'] = [
                'folder' => $folderName,
                'at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ];
            $log->fill(['workflow' => $workflow])->save();

            return false;
        }
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

    private function findFolderIdByName(string $mailbox, string $displayName, string $token): ?string
    {
        $wanted = mb_strtolower($displayName);
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailbox)
            .'/mailFolders?$select=id,displayName&$top=100';

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
                $name = mb_strtolower(trim((string) ($folder['displayName'] ?? '')));
                if ($name === $wanted) {
                    $id = trim((string) ($folder['id'] ?? ''));

                    return $id !== '' ? $id : null;
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
        if (preg_match('/^(physical|court|manual|manual-send)-/i', $messageId)) {
            return false;
        }

        return true;
    }
}
