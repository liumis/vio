<?php

namespace App\Console\Commands;

use App\Support\IncomingFineAttachmentTextExtractor;
use App\Support\IncomingFineMailScanner;
use App\Support\IncomingFineReplyBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DebugFineAttachmentExtract extends Command
{
    protected $signature = 'vio:debug-fine-attachments
                            {--days=7 : Look-back days}
                            {--subject= : Filter subject contains}
                            {--authority= : Filter by authority name contains}
                            {--one-per-authority : Inspect one newest message per matched authority}
                            {--limit=3 : Max messages to inspect (ignored with --one-per-authority)}';

    protected $description = 'Download fine-mail attachments, save under storage/app/fine-attachments, dump extract + field hints';

    public function handle(
        IncomingFineMailScanner $scanner,
        IncomingFineAttachmentTextExtractor $extractor,
        IncomingFineReplyBuilder $builder,
    ): int {
        $days = max(1, (int) $this->option('days'));
        $needle = trim((string) $this->option('subject'));
        $authorityNeedle = trim((string) $this->option('authority'));
        $onePerAuthority = (bool) $this->option('one-per-authority');
        $limit = max(1, (int) $this->option('limit'));

        try {
            $messages = $scanner->scan($days);
        } catch (Throwable $e) {
            $this->error('Inbox scan failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($needle !== '') {
            $messages = array_values(array_filter(
                $messages,
                fn (array $m): bool => str_contains(
                    mb_strtolower((string) ($m['subject'] ?? '')),
                    mb_strtolower($needle)
                )
            ));
        }

        if ($authorityNeedle !== '') {
            $messages = array_values(array_filter(
                $messages,
                fn (array $m): bool => str_contains(
                    mb_strtolower((string) ($m['authority_name'] ?? '')),
                    mb_strtolower($authorityNeedle)
                )
            ));
        }

        if ($onePerAuthority) {
            $picked = [];
            foreach ($messages as $message) {
                $key = (string) ($message['authority_id'] ?? 'unmatched');
                if ($key === '') {
                    $key = 'unmatched';
                }
                if (isset($picked[$key])) {
                    continue;
                }
                $picked[$key] = $message;
            }
            $messages = array_values($picked);
        } else {
            $messages = array_slice($messages, 0, $limit);
        }

        if ($messages === []) {
            $this->warn('No matching fine messages.');

            return self::SUCCESS;
        }

        foreach ($messages as $i => $message) {
            $this->newLine();
            $this->info(sprintf(
                '[%d] %s | %s | %s | attachments=%s',
                $i + 1,
                $message['authority_name'] ?? '—',
                $message['received_at_local'] ?? ($message['received_at'] ?? '?'),
                $message['subject'] ?? '(no subject)',
                ! empty($message['has_attachments']) ? 'yes' : 'no'
            ));
            $this->line('from: '.($message['from_email'] ?? '—'));
            $this->line('id: '.($message['id'] ?? '—'));

            $detail = $extractor->extractDetailedFromMessage($message, persist: true);
            $this->line('saved_dir: '.($detail['saved_dir']
                ? Storage::disk('local')->path($detail['saved_dir'])
                : '(none)'));
            $this->line('combined_chars: '.mb_strlen($detail['text']));

            foreach ($detail['files'] as $file) {
                $this->line(sprintf(
                    '  - %s | %d bytes | %d chars | %s',
                    $file['name'],
                    $file['bytes'],
                    $file['text_chars'],
                    $file['error'] ?? 'ok'
                ));
            }

            $preview = mb_substr($detail['text'], 0, 800);
            if ($preview !== '') {
                $this->newLine();
                $this->line('--- extract preview ---');
                $this->line($preview.(mb_strlen($detail['text']) > 800 ? '…' : ''));
                $this->line('--- end ---');
            }

            $message['attachment_text'] = $detail['text'];
            $ref = $builder->extractReference(
                (string) ($message['subject'] ?? ''),
                (string) ($message['preview'] ?? '')."\n".$detail['text']
            );
            $vehicle = $builder->extractVehicle(
                (string) ($message['subject'] ?? ''),
                (string) ($message['preview'] ?? '')."\n".$detail['text']
            );
            $date = $builder->extractTicketDate(
                (string) ($message['subject'] ?? ''),
                (string) ($message['preview'] ?? '')."\n".$detail['text']
            );

            $this->table(
                ['field', 'value'],
                [
                    ['reference', $ref !== '' ? $ref : '(empty)'],
                    ['vehicle', $vehicle !== '' ? $vehicle : '(empty)'],
                    ['ticket_date', $date !== '' ? $date : '(empty)'],
                ]
            );
        }

        $this->newLine();
        $this->info('Done. Open the saved_dir paths above to inspect PDF + .extract.txt files.');

        return self::SUCCESS;
    }
}
