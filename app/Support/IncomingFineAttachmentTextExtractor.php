<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Downloads inbox attachments, optionally saves them locally, and extracts plain text.
 */
final class IncomingFineAttachmentTextExtractor
{
    private const MAX_ATTACHMENTS = 5;

    private const MAX_BYTES_PER_FILE = 8_000_000;

    private const MAX_TOTAL_CHARS = 80_000;

    public const STORAGE_DIR = 'fine-attachments';

    public function __construct(
        private readonly MicrosoftGraphInbox $inbox = new MicrosoftGraphInbox,
    ) {
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     text: string,
     *     files: list<array{name:string,path:string,bytes:int,text_chars:int,source?:string,error:?string}>,
     *     saved_dir: string|null
     * }
     */
    public function extractDetailedFromMessage(array $message, bool $persist = true): array
    {
        $messageId = trim((string) ($message['id'] ?? ''));
        $empty = ['text' => '', 'files' => [], 'saved_dir' => null];

        if ($messageId === '') {
            return $empty;
        }

        if (empty($message['has_attachments'])) {
            return $empty;
        }

        try {
            $mailbox = $this->inbox->mailbox();
            $attachments = $this->inbox->listFileAttachments($messageId, $mailbox);
        } catch (Throwable $e) {
            Log::warning('Fine attachment list failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }

        $dir = $persist ? $this->storageDirForMessage($messageId, $message) : null;
        if ($dir !== null) {
            Storage::disk('local')->makeDirectory($dir);
        }

        $chunks = [];
        $files = [];
        $totalChars = 0;
        $processed = 0;

        foreach ($attachments as $attachment) {
            if ($processed >= self::MAX_ATTACHMENTS || $totalChars >= self::MAX_TOTAL_CHARS) {
                break;
            }

            $name = $this->safeFileName((string) ($attachment['name'] ?? 'attachment.bin'));
            $contentType = strtolower((string) ($attachment['contentType'] ?? ''));
            $size = (int) ($attachment['size'] ?? 0);
            $fileMeta = [
                'name' => $name,
                'path' => '',
                'bytes' => 0,
                'text_chars' => 0,
                'source' => null,
                'error' => null,
            ];

            if (! $this->isSupported($name, $contentType)) {
                $fileMeta['error'] = 'unsupported type (need pdf/txt/jpeg/png)';
                $files[] = $fileMeta;

                continue;
            }

            if ($size > self::MAX_BYTES_PER_FILE) {
                $fileMeta['error'] = 'file too large';
                $files[] = $fileMeta;

                continue;
            }

            try {
                $binary = $this->inbox->downloadAttachmentContent(
                    $messageId,
                    (string) $attachment['id'],
                    $mailbox,
                );
            } catch (Throwable $e) {
                $fileMeta['error'] = 'download failed: '.$e->getMessage();
                $files[] = $fileMeta;
                Log::warning('Fine attachment download failed', [
                    'message_id' => $messageId,
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $fileMeta['bytes'] = strlen($binary);

            if ($binary === '' || strlen($binary) > self::MAX_BYTES_PER_FILE) {
                $fileMeta['error'] = 'empty or oversized after download';
                $files[] = $fileMeta;

                continue;
            }

            $absolutePath = null;
            if ($dir !== null) {
                $relative = $dir.'/'.$name;
                Storage::disk('local')->put($relative, $binary);
                $fileMeta['path'] = $relative;
                $absolutePath = Storage::disk('local')->path($relative);
            } else {
                $tmp = tempnam(sys_get_temp_dir(), 'fineocr_');
                if (is_string($tmp)) {
                    $absolutePath = $tmp.'.'.(pathinfo($name, PATHINFO_EXTENSION) ?: 'pdf');
                    @rename($tmp, $absolutePath);
                    file_put_contents($absolutePath, $binary);
                }
            }

            $extract = $this->textFromBinary($binary, $name, $contentType, $absolutePath);
            $text = trim(preg_replace("/[ \t]+/u", ' ', $extract['text']) ?? $extract['text']);
            $source = $extract['source'];

            if ($dir === null && is_string($absolutePath) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            if ($text === '') {
                $fileMeta['error'] = $source === 'ocr-missing-key'
                    ? 'scanned PDF — set OCR_SPACE_API_KEY (Futura/SS style)'
                    : ($source === 'ocr-failed'
                        ? 'OCR returned empty'
                        : 'no extractable text (scanned/image PDF)');
                $files[] = $fileMeta;

                if ($dir !== null) {
                    Storage::disk('local')->put(
                        $dir.'/'.$name.'.extract.txt',
                        '(empty — '.$fileMeta['error'].")\n"
                    );
                }

                continue;
            }

            if ($dir !== null) {
                Storage::disk('local')->put(
                    $dir.'/'.$name.'.extract.txt',
                    "[source={$source}]\n".$text
                );
            }

            $remaining = self::MAX_TOTAL_CHARS - $totalChars;
            if (mb_strlen($text) > $remaining) {
                $text = mb_substr($text, 0, $remaining);
            }

            $fileMeta['text_chars'] = mb_strlen($text);
            $fileMeta['source'] = $source;
            $files[] = $fileMeta;
            $chunks[] = $text;
            $totalChars += $fileMeta['text_chars'];
            $processed++;
        }

        // Include attachment filenames — Julianus PDFs are often "716023119-7065399.pdf".
        $fileNames = collect($files)
            ->pluck('name')
            ->filter()
            ->implode("\n");
        if ($fileNames !== '') {
            array_unshift($chunks, $fileNames);
        }

        $combined = trim(implode("\n\n", $chunks));

        if ($dir !== null && $combined !== '') {
            Storage::disk('local')->put($dir.'/combined.extract.txt', $combined);
        }

        Log::info('Fine attachment extract', [
            'message_id' => $messageId,
            'saved_dir' => $dir,
            'files' => collect($files)->map(fn ($f) => [
                'name' => $f['name'],
                'chars' => $f['text_chars'],
                'error' => $f['error'],
            ])->all(),
            'total_chars' => mb_strlen($combined),
        ]);

        return [
            'text' => $combined,
            'files' => $files,
            'saved_dir' => $dir,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function extractFromMessage(array $message): string
    {
        return $this->extractDetailedFromMessage($message, persist: true)['text'];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function storageDirForMessage(string $messageId, array $message): string
    {
        $stamp = now()->format('Ymd-His');
        $ref = preg_replace('/\D+/', '', (string) ($message['subject'] ?? '')) ?: 'noref';
        $ref = Str::limit($ref, 20, '');
        $idSlug = Str::limit(preg_replace('/[^A-Za-z0-9_-]+/', '', $messageId) ?: 'msg', 24, '');

        return self::STORAGE_DIR.'/'.$stamp.'-'.$ref.'-'.$idSlug;
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN.\-_ ]+/u', '_', $name) ?: 'attachment.bin';
        $name = trim($name);

        return $name !== '' ? Str::limit($name, 120, '') : 'attachment.bin';
    }

    private function isSupported(string $name, string $contentType): bool
    {
        $lower = strtolower($name);

        if (str_ends_with($lower, '.pdf') || str_contains($contentType, 'pdf')) {
            return true;
        }

        if (str_ends_with($lower, '.txt') || str_contains($contentType, 'text/plain')) {
            return true;
        }

        return $this->isImage($name, $contentType);
    }

    private function isImage(string $name, string $contentType): bool
    {
        $lower = strtolower($name);
        $contentType = strtolower($contentType);

        if (str_contains($contentType, 'image/')) {
            return true;
        }

        foreach (['.jpg', '.jpeg', '.png', '.webp', '.gif', '.tif', '.tiff', '.bmp'] as $ext) {
            if (str_ends_with($lower, $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{text:string,source:string}
     */
    private function textFromBinary(string $binary, string $name, string $contentType, ?string $absolutePath): array
    {
        $lower = strtolower($name);

        if (str_ends_with($lower, '.txt') || str_contains($contentType, 'text/plain')) {
            return [
                'text' => $this->normalizeEncoding($binary),
                'source' => 'txt',
            ];
        }

        if ($this->isImage($name, $contentType)) {
            return $this->textFromOcr($absolutePath);
        }

        $pdfText = $this->textFromPdf($binary);
        if ($pdfText !== '' && mb_strlen($pdfText) >= 40) {
            return ['text' => $pdfText, 'source' => 'pdf'];
        }

        $ocr = $this->textFromOcr($absolutePath);
        if ($ocr['text'] !== '') {
            return $ocr;
        }

        if ($pdfText !== '') {
            return ['text' => $pdfText, 'source' => 'pdf-weak'];
        }

        return $ocr;
    }

    private function textFromPdf(string $binary): string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseContent($binary);
            $text = (string) $pdf->getText();

            return $this->normalizeEncoding($text);
        } catch (Throwable $e) {
            Log::warning('PDF parse failed', ['error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @return array{text:string,source:string}
     */
    private function textFromOcr(?string $absolutePath): array
    {
        $ocr = app(OcrSpaceClient::class);
        if (! $ocr->isConfigured()) {
            return ['text' => '', 'source' => 'ocr-missing-key'];
        }

        if ($absolutePath === null || ! is_file($absolutePath)) {
            return ['text' => '', 'source' => 'ocr-failed'];
        }

        $text = $this->normalizeEncoding($ocr->extractText($absolutePath, 'eng'));

        return [
            'text' => $text,
            'source' => $text !== '' ? 'ocr' : 'ocr-failed',
        ];
    }

    private function normalizeEncoding(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-13, ISO-8859-1, Windows-1257');
            $text = is_string($converted) ? $converted : $text;
        }

        return trim(str_replace("\0", '', $text));
    }
}
