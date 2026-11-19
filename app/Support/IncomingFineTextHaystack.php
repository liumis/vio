<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Build the text blob used to extract plate / reference / ticket datetime.
 */
final class IncomingFineTextHaystack
{
    /**
     * @param  array<string, mixed>  $message
     * @param  array{text?:string,saved_dir?:?string,files?:list<array<string,mixed>>}  $extract
     */
    public function build(array $message, array $extract = [], string $extraBody = ''): string
    {
        $parts = [
            (string) ($message['preview'] ?? ''),
            (string) ($message['body_text'] ?? ''),
            $extraBody,
            (string) ($extract['text'] ?? ''),
        ];

        // Re-parse local email-print / PDFs when attachment extract was empty
        // (letter-form Unipark/JUDU often have plate only in the email body PDF).
        $attachmentText = trim((string) ($extract['text'] ?? ''));
        if ($attachmentText === '' || strlen($attachmentText) < 40) {
            $parts[] = $this->textFromSavedDir($extract['saved_dir'] ?? null);
        }

        $haystack = trim(implode("\n", array_filter(
            array_map(static fn (string $p): string => trim($p), $parts),
            static fn (string $p): bool => $p !== '',
        )));

        return $haystack;
    }

    /**
     * Fetch Graph plain-text body when missing (Unipark HTML letters, etc.).
     *
     * @param  array<string, mixed>  $message
     */
    public function fetchBodyText(array $message): string
    {
        $messageId = trim((string) ($message['id'] ?? ''));
        if ($messageId === '') {
            return trim((string) ($message['preview'] ?? ''));
        }

        try {
            $text = app(MicrosoftGraphInbox::class)->getMessageBodyText($messageId);
        } catch (Throwable) {
            $text = '';
        }

        return trim($text) !== '' ? trim($text) : trim((string) ($message['preview'] ?? ''));
    }

    private function textFromSavedDir(mixed $savedDir): string
    {
        $dir = trim(str_replace('\\', '/', (string) $savedDir), '/');
        if ($dir === '') {
            return '';
        }

        $absolute = Storage::disk('local')->path($dir);
        if (! is_dir($absolute)) {
            return '';
        }

        $chunks = [];
        $files = glob($absolute.DIRECTORY_SEPARATOR.'*.{pdf,txt,html,htm}', GLOB_BRACE) ?: [];
        // Fallback if GLOB_BRACE unsupported on Windows.
        if ($files === []) {
            $files = array_merge(
                glob($absolute.DIRECTORY_SEPARATOR.'*.pdf') ?: [],
                glob($absolute.DIRECTORY_SEPARATOR.'*.txt') ?: [],
                glob($absolute.DIRECTORY_SEPARATOR.'*.html') ?: [],
            );
        }

        // Prefer email-print.pdf first.
        usort($files, static function (string $a, string $b): int {
            $ap = str_contains(strtolower(basename($a)), 'email-print') ? 0 : 1;
            $bp = str_contains(strtolower(basename($b)), 'email-print') ? 0 : 1;

            return $ap <=> $bp;
        });

        foreach (array_slice($files, 0, 4) as $file) {
            if (! is_file($file) || filesize($file) > 8_000_000) {
                continue;
            }
            $name = strtolower(basename($file));
            try {
                if (str_ends_with($name, '.pdf')) {
                    $text = (string) (new Parser)->parseFile($file)->getText();
                } else {
                    $text = (string) file_get_contents($file);
                }
            } catch (Throwable) {
                continue;
            }
            $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text !== '') {
                $chunks[] = $text;
            }
            if (strlen(implode("\n", $chunks)) > 60_000) {
                break;
            }
        }

        return trim(implode("\n", $chunks));
    }
}
