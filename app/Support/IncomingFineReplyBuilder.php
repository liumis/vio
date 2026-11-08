<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\EmailSetting;
use App\Models\FineProcessLog;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Builds an authority reply draft from an inbox fine message (+ optional Wheelsys row).
 */
final class IncomingFineReplyBuilder
{
    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>|null  $wheelsys
     * @return array{
     *     to: string,
     *     from_email: string,
     *     from_name: string,
     *     reply_to: string,
     *     subject: string,
     *     body_text: string,
     *     body_html: string,
     *     authority_name: string,
     *     authority_id: int|null,
     *     reference: string,
     *     vehicle_hint: string,
     *     ticket_date_hint: string,
     *     missing_fields: list<string>,
     *     wheelsys: array<string, mixed>|null
     * }
     */
    /**
     * Build a reply draft from a stored fine log (physical mail / imported).
     *
     * @param  array<string, mixed>|null  $wheelsys
     * @return array<string, mixed>
     */
    public function buildForProcessLog(FineProcessLog $log, ?array $wheelsys = null): array
    {
        $wheelsys = is_array($wheelsys) ? $wheelsys : [];
        if (($wheelsys['vehicle'] ?? '') === '' && filled($log->vehicle)) {
            $wheelsys['vehicle'] = $log->vehicle;
        }
        if (($wheelsys['driver'] ?? '') === '' && filled($log->driver_name)) {
            $wheelsys['driver'] = $log->driver_name;
        }
        if (($wheelsys['ticket_date'] ?? '') === '' && $log->ticket_date) {
            $wheelsys['ticket_date'] = $log->ticket_date->format('Y-m-d');
        }
        if (($wheelsys['ticket_number'] ?? '') === '' && filled($log->reference)) {
            $wheelsys['ticket_number'] = $log->reference;
        }
        if (($wheelsys['agr_no'] ?? '') === '' && filled($log->agr_no)) {
            $wheelsys['agr_no'] = $log->agr_no;
        }

        $fromEmail = trim((string) ($log->from_email ?? ''));
        if ($fromEmail === '' && $log->authority_id) {
            $fromEmail = trim((string) (Authority::query()->whereKey($log->authority_id)->value('from_email') ?? ''));
        }
        if ($fromEmail === '' && filled($log->reference) && str_starts_with(strtoupper((string) $log->reference), 'CAN')) {
            $fromEmail = 'canard@gitd.gov.pl';
        }

        return $this->build([
            'authority_id' => $log->authority_id,
            'from_email' => $fromEmail,
            'subject' => (string) ($log->reference ?: $log->subject ?: ''),
            'preview' => trim(implode("\n", array_filter([
                (string) ($log->reference ?? ''),
                (string) ($log->vehicle ?? ''),
                (string) ($log->subject ?? ''),
            ]))),
            'attachment_text' => (string) ($log->reference ?? ''),
        ], $wheelsys);
    }

    public function build(array $message, ?array $wheelsys = null): array
    {
        $fromEmail = trim((string) ($message['from_email'] ?? ''));
        $authority = null;

        if (! empty($message['authority_id'])) {
            $authority = Authority::query()->find((int) $message['authority_id']);
        }

        if (! $authority && $fromEmail !== '') {
            $authority = AuthorityMatcher::findForEmail($fromEmail);
        }

        $emailSubject = trim((string) ($message['subject'] ?? ''));
        $preview = trim((string) ($message['preview'] ?? ''));
        $attachmentText = trim((string) ($message['attachment_text'] ?? ''));
        if ($attachmentText === '' && ! empty($message['has_attachments']) && filled($message['id'] ?? null)) {
            $attachmentText = app(IncomingFineAttachmentTextExtractor::class)->extractFromMessage($message);
        }

        // HTML notices / photo-only mails: bodyPreview is truncated — pull full text body
        // even when JPEG OCR already returned a short plate snippet.
        $haystackProbe = trim($preview."\n".$attachmentText);
        $needsBody = mb_strlen($attachmentText) < 400
            || $this->extractReference($emailSubject, $haystackProbe) === ''
            || $this->extractTicketDate($emailSubject, $haystackProbe) === '';

        if ($needsBody && filled($message['id'] ?? null)) {
            $bodyText = trim((string) ($message['body_text'] ?? ''));
            if ($bodyText === '') {
                try {
                    $bodyText = app(MicrosoftGraphInbox::class)->getMessageBodyText((string) $message['id']);
                } catch (\Throwable) {
                    $bodyText = '';
                }
            }
            if ($bodyText !== '') {
                $attachmentText = trim($attachmentText."\n".$bodyText);
            }
        }

        $textHaystack = trim($preview."\n".$attachmentText);

        if (! $authority) {
            $authority = AuthorityMatcher::findFromHaystack($emailSubject."\n".$textHaystack);
        }

        if (! $authority) {
            throw new RuntimeException(
                'No Authority matched this sender. Add email_pattern for: '
                .($fromEmail !== '' ? $fromEmail : '(empty from)')
            );
        }

        $template = trim((string) ($authority->mail_template ?? ''));
        if ($template === '') {
            throw new RuntimeException('Mail template is empty for authority: '.$authority->name);
        }

        $reference = $this->extractReference($emailSubject, $textHaystack);

        if ($reference === '' && filled($wheelsys['ticket_number'] ?? null)) {
            $reference = (string) $wheelsys['ticket_number'];
        }

        $vehicleHint = $this->extractVehicle($emailSubject, $textHaystack);
        if ($vehicleHint === '' && filled($wheelsys['vehicle'] ?? null)) {
            $vehicleHint = strtoupper((string) $wheelsys['vehicle']);
        }

        $ticketDateHint = $this->extractTicketDate($emailSubject, $textHaystack);
        if ($ticketDateHint === '' && filled($wheelsys['ticket_date'] ?? null)) {
            $ticketDateHint = (string) $wheelsys['ticket_date'];
        }

        $driver = (string) ($wheelsys['driver'] ?? '');
        $vehicle = strtoupper((string) ($wheelsys['vehicle'] ?? $vehicleHint));
        $address = trim(implode(', ', array_filter([
            (string) ($wheelsys['driver_address'] ?? ''),
            (string) ($wheelsys['driver_city'] ?? ''),
            (string) ($wheelsys['driver_country'] ?? ''),
        ])));

        $license = (string) ($wheelsys['driver_license'] ?? '');
        $birthDate = (string) ($wheelsys['birth_date'] ?? '');

        $vars = [
            '[%1%]' => $reference,
            '[%2%]' => $this->boldMark($driver),
            '[%3%]' => (string) ($wheelsys['ticket_date'] ?? ''),
            '[%4%]' => $this->boldMark($vehicle),
            '[%5%]' => $this->boldMark($license),
            '[%6%]' => $this->boldMark($driver),
            '[%7%]' => $this->boldMark($birthDate),
            '[%8%]' => (string) ($wheelsys['customer_email'] ?? ''),
            '[%9%]' => $this->boldMark($address),
            '[%10%]' => (string) ($wheelsys['driver_telephone'] ?? ''),
            '[%11%]' => (string) ($wheelsys['licence_issue_date'] ?? ''),
            '[%12%]' => (string) ($wheelsys['driver_country'] ?? ''),
        ];

        $bodyText = strtr($template, $vars);
        $config = EmailSetting::graphConfig();

        $to = $this->resolveReplyToEmail(
            authorityTo: (string) ($authority->to_email ?? ''),
            authorityMain: (string) ($authority->main_email ?? ''),
            authorityFrom: (string) ($authority->from_email ?? ''),
            senderFrom: $fromEmail,
        );
        // Only ANR/ROIK is portal-only; other noreply senders (Unipark, Judu…) still get email replies to to_email.
        $replyChannel = str_contains(strtolower((string) $authority->email_pattern), 'anr.gov')
            ? 'epaslaugos'
            : 'email';
        $infoPdfTemplate = app(AuthorityInfoPdfGenerator::class)->resolveTemplate($authority);
        // Poland CAN letters (instruction): Subject = bylos nr. + valst. nr.
        $isPoland = str_contains(strtolower((string) ($authority->email_pattern ?? '')), 'gitd.gov.pl')
            || str_starts_with(strtoupper($reference), 'CAN');
        if ($isPoland && $reference !== '' && $vehicle !== '') {
            $subject = trim($reference.' '.$vehicle);
        } elseif ($reference !== '') {
            $subject = $reference;
        } else {
            $subject = 'Re: '.($emailSubject !== '' ? $emailSubject : 'Violation notification');
        }

        $branded = $this->renderNeutralHtml($subject, $bodyText);

        $missing = [];
        foreach ([
            '[%2%]' => 'driver',
            '[%4%]' => 'vehicle',
            '[%7%]' => 'birth_date',
            '[%8%]' => 'email',
        ] as $token => $label) {
            $raw = match ($token) {
                '[%2%]' => $driver,
                '[%4%]' => $vehicle,
                '[%7%]' => $birthDate,
                default => trim((string) ($vars[$token] ?? '')),
            };
            if (str_contains($template, $token) && trim($raw) === '') {
                $missing[] = $label;
            }
        }

        return [
            'to' => $to,
            'from_email' => $config['from_email'],
            'from_name' => $config['from_name'],
            'reply_to' => $config['reply_to'],
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $branded,
            'authority_name' => (string) $authority->name,
            'authority_id' => $authority->id,
            'reference' => $reference,
            'vehicle_hint' => $vehicleHint,
            'ticket_date_hint' => $ticketDateHint,
            'ticket_time_hint' => $this->extractTicketTime($emailSubject, $textHaystack),
            'attachment_text_used' => $attachmentText !== '',
            'reply_channel' => $replyChannel,
            'info_pdf_template' => $infoPdfTemplate,
            'info_pdf_file_name' => $infoPdfTemplate !== null && $reference !== ''
                ? preg_replace('/[\/\\\\:*?"<>|]+/', '-', $reference).'.pdf'
                : null,
            'missing_fields' => $missing,
            'wheelsys' => $wheelsys,
        ];
    }

    /**
     * Never fall back to noreply senders (ANR auto-anr-no-reply, etc.).
     */
    public function resolveReplyToEmail(
        string $authorityTo,
        string $authorityMain,
        string $authorityFrom,
        string $senderFrom,
    ): string {
        foreach ([$authorityTo, $authorityMain, $authorityFrom, $senderFrom] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || $this->isNoReplyEmail($candidate)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    public function isNoReplyEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(no[\-._]?reply|noreply|auto[\-._]?.*no[\-._]?reply|donotreply|do[\-._]?not[\-._]?reply)@/i',
            $email
        ) || str_contains($email, 'no-reply@')
            || str_contains($email, 'noreply@')
            || str_contains($email, 'auto-anr-no-reply@');
    }

    /**
     * Combine date + time hints into an ISO-like datetime when possible.
     */
    public function extractTicketDateTime(string $subject, string $preview = ''): string
    {
        $date = $this->extractTicketDate($subject, $preview);
        if ($date === '') {
            return '';
        }

        $time = $this->extractTicketTime($subject, $preview);

        return $time !== '' ? $date.'T'.$time.':00' : $date;
    }

    /**
     * Offense time as HH:MM when present (e.g. "15 val. 34 min.").
     */
    public function extractTicketTime(string $subject, string $preview = ''): string
    {
        $haystack = $subject."\n".$preview;

        if (preg_match('/\b(\d{1,2})\s*val\.?\s*(\d{1,2})\s*min/iu', $haystack, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        // "03/09/2026 at 16:42 traffic offence was committed"
        if (preg_match(
            '/\b(\d{2})\/(\d{2})\/(20\d{2})\s+at\s+([01]?\d|2[0-3]):([0-5]\d)\b.{0,40}(?:traffic\s+)?offence/iu',
            $haystack,
            $m
        )) {
            return sprintf('%02d:%02d', (int) $m[4], (int) $m[5]);
        }

        // "2026-10-14 17:10 val." / "nustatė, kad 2026-10-14 17:10"
        if (preg_match('/\b20\d{2}[.\-\/]\d{2}[.\-\/]\d{2}\s+([01]?\d|2[0-3]):([0-5]\d)\s*val\.?\b/iu', $haystack, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        if (preg_match('/nustat[eė]\s*,?\s*kad\s+20\d{2}[.\-\/]\d{2}[.\-\/]\d{2}\s+([01]?\d|2[0-3]):([0-5]\d)\b/iu', $haystack, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    /**
     * Rebuild branded HTML after manual subject/body edits.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function refreshHtml(array $draft): array
    {
        $subject = trim((string) ($draft['subject'] ?? ''));
        $bodyText = (string) ($draft['body_text'] ?? '');
        $draft['body_html'] = $this->renderNeutralHtml($subject, $bodyText);

        return $draft;
    }

    public function renderNeutralHtml(string $subject, string $contentText): string
    {
        return View::make('emails.authority-reply-neutral', [
            'subject' => $subject,
            'contentText' => $contentText,
        ])->render();
    }

    /**
     * Mark value for HTML <strong> in the neutral email template (**value**).
     */
    private function boldMark(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return '**'.$value.'**';
    }

    public function extractReference(string $subject, string $preview = ''): string
    {
        $haystack = $subject."\n".$preview;

        if (preg_match('/\bROIK\s*:?\s*([0-9]{6,})\b/iu', $haystack, $m)) {
            return 'ROIK '.$m[1];
        }

        // VMI reminders: table dumps "2026-10-06 26313998465" after a (ROIK) header.
        if (preg_match('/\(ROIK\).{0,400}?\b(26\d{9})\b/isu', $haystack, $m)) {
            return 'ROIK '.$m[1];
        }

        if (preg_match('/\bROIK\b.{0,200}?\b(26\d{9})\b/isu', $haystack, $m)) {
            return 'ROIK '.$m[1];
        }

        // Filename / payment slips often carry the 11-digit ROIK alone.
        if (preg_match('/\b(26\d{9})\b/', $haystack, $m)) {
            return 'ROIK '.$m[1];
        }

        // Poland GITD physical letters: reference starts with CAN…
        if (preg_match('/\b(CAN[A-Z0-9\-]{4,})\b/iu', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        // Latvian CPP case / decision numbers (prefer over company codes in PDF body).
        if (preg_match(
            '/(?:Case\s+No\.?|Decision\s+Nr\.?|notification\s+No\.?|Offence\s+Case\s+No\.?)\s*[:\-]?\s*([A-Z]{1,3}\d{5,})/iu',
            $haystack,
            $m
        )) {
            return strtoupper($m[1]);
        }

        if (preg_match('/\b(GA\d{6,})\b/iu', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        // Latvian CPP correspondence refs: RE: PZ00245564
        if (preg_match('/\b(PZ\d{6,})\b/iu', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        // Vilnius / ANAM style: A51-186773
        if (preg_match('/\b(A\d{1,4}-\d{3,})\b/u', $haystack, $m)) {
            return $m[1];
        }

        // Judu / similar: Pranešimo Nr. A1424058 (no hyphen)
        if (preg_match('/(?:Prane[sš]imo\s+Nr\.?|Nr\.?)\s*(A\d{6,})\b/iu', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/\b(A\d{7,})\b/u', $haystack, $m)) {
            return $m[1];
        }

        // Unipark style: Pranešimas Nr. E9WB4AK
        if (preg_match('/(?:Prane[sš]imas\s+Nr\.?|Prane[sš]imo\s+Nr\.?)\s*([A-Z0-9]{5,12})\b/iu', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        // Julianus: "Pranešimo kodas: 716057729" / "mokėjimo paskirtis: pranešimo kodas 716023119"
        if (preg_match('/Prane[sš]imo\s+kodas\s*[:\-]?\s*([0-9]{6,})\b/iu', $haystack, $m)) {
            return $m[1];
        }

        // Labeled numeric refs only (avoid bare company codes like 300138495).
        if (preg_match(
            '/(?:Prane[sš]imo\s+Nr\.?|pagal\s+Nr\.?|registracijos\s+Nr\.?|Nr\.?)\s*[:\-]?\s*([0-9]{5,})/iu',
            $haystack,
            $m
        )) {
            return $m[1];
        }

        // Julianus PDF filenames / OCR: 716023119-7065399.pdf
        if (preg_match('/\b(71\d{7})\b/', $haystack, $m)) {
            return $m[1];
        }

        return '';
    }

    public function extractVehicle(string $subject, string $preview = ''): string
    {
        $haystack = strtoupper($subject."\n".$preview);

        if (preg_match(
            '/(?:VALSTYBIN(?:IS|IO)?\s+NUMER(?:IS|IO)|VALST(?:YBINIS)?\s*NR\.?|VALSTYBINIS\s+NR\.?|TRANSPORTO\s+PRIEMON[EĖ]|VEHICLE|PLATE|REG(?:ISTRATION)?)\s*[:\-]?\s*([A-Z]{2,3}[\s\-]?\d{2,4})/u',
            $haystack,
            $m
        )) {
            $plate = preg_replace('/[\s\-]+/', '', $m[1]) ?? $m[1];
            if (! $this->isFalsePositivePlate($plate)) {
                return $plate;
            }
        }

        if (preg_match('/\b([A-Z]{3}\d{3})\b/', $haystack, $m) && ! $this->isFalsePositivePlate($m[1])) {
            return $m[1];
        }

        if (preg_match_all('/\b([A-Z]{2,3}[\s-]?\d{2,4})\b/', $haystack, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $full) {
                $raw = $matches[1][$idx][0];
                $offset = (int) $full[1];
                // Hyphenated tokens are usually document nos (VA-193), not plates.
                if (str_contains($full[0], '-')) {
                    continue;
                }
                // preg offset is bytes — use substr, not mb_substr.
                $before = strtolower(substr($haystack, max(0, $offset - 40), 40));
                if (str_contains($before, 'nr.') || str_contains($before, 'isakym') || str_contains($before, 'nutarim')) {
                    continue;
                }
                $plate = preg_replace('/[\s-]+/', '', $raw) ?? $raw;
                if (! $this->isFalsePositivePlate($plate)) {
                    return $plate;
                }
            }
        }

        return '';
    }

    private function isFalsePositivePlate(string $plate): bool
    {
        $plate = strtoupper(trim($plate));

        // IBAN country + check digits (LT08 4040…), not a vehicle plate.
        if (preg_match('/^(LT|LV|EE|EU|IBAN)\d{2}$/', $plate)) {
            return true;
        }

        // Legal article refs: "ANK 669 straipsnyje".
        if (preg_match('/^(ANK|ANKL|ADM)\d+$/', $plate)) {
            return true;
        }

        // Typical LT/LV plates: ABC123 / AB1234 — reject shorter junk (IO20, etc.).
        if (! preg_match('/^([A-Z]{3}\d{2,4}|[A-Z]{2}\d{3,4})$/', $plate)) {
            return true;
        }

        return false;
    }

    /**
     * Prefer explicit ticket/violation dates from email/attachment text (Y-m-d for date inputs).
     */
    public function extractTicketDate(string $subject, string $preview = ''): string
    {
        $haystack = $subject."\n".$preview;

        // ANR prose near "Nusižengimo padarymo vieta, data ir laikas: ... 2026 m. lapkričio mėn. 3 d."
        // Allow OCR typos: mén/men instead of mėn, missing diacritics on month names.
        if (preg_match(
            '/Nusi[zž]engimo\s+padarymo\s+vieta,?\s*data\s+ir\s+laikas\s*:(.{0,280}?)(20\d{2})\s*m\.\s*([A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+)\s*(?:m[eėé]n\.?\s*)?(\d{1,2})\s*d\./iu',
            $haystack,
            $m
        )) {
            $iso = $this->lithuanianMonthDateToIso($m[2], $m[3], $m[4]);
            if ($iso !== '' && $this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // Vilnius prose: "nustatė, kad 2026-10-14 17:10 val."
        if (preg_match(
            '/nustat[eė]\s*,?\s*kad\s+(20\d{2})[.\-\/](\d{2})[.\-\/](\d{2})\s+\d{1,2}:\d{2}/iu',
            $haystack,
            $m
        )) {
            $iso = $m[1].'-'.$m[2].'-'.$m[3];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // VMI table row: "2026-10-06 26313998465" (decision date + ROIK).
        if (preg_match('/\b(20\d{2})-(\d{2})-(\d{2})\s+26\d{9}\b/', $haystack, $m)) {
            $iso = $m[1].'-'.$m[2].'-'.$m[3];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // Unipark / Julianus: "Skolos susidarymo data ir laikas 2026-10-19…" / "SKOLOS SUSIDARYMO DATA - 2026.08.15."
        if (preg_match(
            '/Skolos\s+susidarymo\s+data(?:\s+ir\s+laikas)?\s*[:\-]?\s*(20\d{2})[.\-\/](\d{2})[.\-\/](\d{2})/iu',
            $haystack,
            $m
        )) {
            $iso = $m[1].'-'.$m[2].'-'.$m[3];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // Julianus: "… PAŽEIDIMO FIKSAVIMO DATA - KIA MRH986;2026-08-15T11:12:55"
        // PDF OCR: "… fiksavimo data: SKODA NNO302;2026-09-30:"
        if (preg_match(
            '/fiksavimo\s+data\s*[:\-]?\s*.{0,40}?;\s*(20\d{2})[.\-\/](\d{2})[.\-\/](\d{2})/iu',
            $haystack,
            $m
        )) {
            $iso = $m[1].'-'.$m[2].'-'.$m[3];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        if (preg_match('/\b[A-Z]{2,3}\d{2,4}\s*;\s*(20\d{2})[.\-\/](\d{2})[.\-\/](\d{2})(?:T|\b|:)/u', $haystack, $m)) {
            $iso = $m[1].'-'.$m[2].'-'.$m[3];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // Julianus camera filenames: 20260906143426188-ID_…-NTH054_B.jpeg
        if (preg_match('/\b(20\d{2})(\d{2})(\d{2})\d{6,}[-_]/u', $haystack, $m)) {
            $iso = $m[1].'-'.$m[2].'-'.$m[3];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // English LV offence line: "03/09/2026 at 16:42 traffic offence was committed"
        if (preg_match(
            '/\b(\d{2})\/(\d{2})\/(20\d{2})\s+at\s+\d{1,2}:\d{2}.{0,40}(?:traffic\s+)?offence/iu',
            $haystack,
            $m
        )) {
            $iso = $m[3].'-'.$m[2].'-'.$m[1];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        if (preg_match(
            '/(?:traffic\s+)?offence\s+was\s+committed.{0,40}?\b(\d{2})\/(\d{2})\/(20\d{2})/iu',
            $haystack,
            $m
        )) {
            $iso = $m[3].'-'.$m[2].'-'.$m[1];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        $labeledPatterns = [
            '/(?:PAŽEIDIMO\s+DATA|NUSIŽENGIMO\s+DATA|DATA\s+IR\s+LAIKAS|DATA\s*(?:IR|,)?\s*LAIKAS|VIOLATION\s+DATE|TICKET\s+DATE|DATE\s+OF\s+(?:OFFENCE|OFFENSE))\s*[:\-]?\s*(20\d{2})[.\-\/](\d{2})[.\-\/](\d{2})/iu',
            '/(?:PAŽEIDIMO\s+DATA|NUSIŽENGIMO\s+DATA|DATA\s+IR\s+LAIKAS|VIOLATION\s+DATE|TICKET\s+DATE)\s*[:\-]?\s*(\d{2})[.\-\/](\d{2})[.\-\/](20\d{2})/iu',
        ];

        foreach ($labeledPatterns as $i => $pattern) {
            if (! preg_match($pattern, $haystack, $m)) {
                continue;
            }

            $iso = $i === 0
                ? $m[1].'-'.$m[2].'-'.$m[3]
                : $m[3].'-'.$m[2].'-'.$m[1];
            if ($this->isPlausibleTicketDate($iso)) {
                return $iso;
            }
        }

        // ISO/dotted date followed by clock time (offense), skip metadata copy dates.
        if (preg_match_all(
            '/\b(20\d{2})[.\-\/](\d{2})[.\-\/](\d{2})\s+(\d{1,2}:\d{2})\b/u',
            $haystack,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $iso = $m[1].'-'.$m[2].'-'.$m[3];
                if ($this->looksLikeMetadataDateContext($haystack, $iso)) {
                    continue;
                }

                return $iso;
            }
        }

        // Lithuanian long date with time: "... 2026 m. spalio mėn. 14 d. 17 val. 10 min."
        if (preg_match_all(
            '/\b(20\d{2})\s*m\.\s*([A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+)\s*(?:m[eėé]n\.?\s*)?(\d{1,2})\s*d\.\s*\d{1,2}\s*val/iu',
            $haystack,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach (array_reverse($matches) as $m) {
                $iso = $this->lithuanianMonthDateToIso($m[1], $m[2], $m[3]);
                if ($iso !== '' && $this->isPlausibleTicketDate($iso) && ! $this->looksLikeMetadataDateContext($haystack, $iso)) {
                    return $iso;
                }
            }
        }

        // Generic Lithuanian long date — prefer later (document header often first).
        if (preg_match_all(
            '/\b(20\d{2})\s*m\.\s*([A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+)\s*(?:m[eėé]n\.?\s*)?(\d{1,2})\s*d\./iu',
            $haystack,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach (array_reverse($matches) as $m) {
                $iso = $this->lithuanianMonthDateToIso($m[1], $m[2], $m[3]);
                if ($iso !== '' && $this->isPlausibleTicketDate($iso) && ! $this->looksLikeMetadataDateContext($haystack, $iso)) {
                    return $iso;
                }
            }
        }

        // Last resort: unlabeled ISO dates, skipping metadata / law citation years.
        if (preg_match_all('/\b(20\d{2})-(\d{2})-(\d{2})\b/', $haystack, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $iso = $m[1].'-'.$m[2].'-'.$m[3];
                if ($this->looksLikeMetadataDateContext($haystack, $iso)) {
                    continue;
                }

                return $iso;
            }
        }

        if (preg_match_all('/\b(\d{2})\.(\d{2})\.(20\d{2})\b/', $haystack, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $iso = $m[3].'-'.$m[2].'-'.$m[1];
                if ($this->looksLikeMetadataDateContext($haystack, $iso)) {
                    continue;
                }

                return $iso;
            }
        }

        return '';
    }

    private function looksLikeMetadataDateContext(string $haystack, string $iso): bool
    {
        if (! $this->isPlausibleTicketDate($iso)) {
            return true;
        }

        $pos = strpos($haystack, $iso);
        if ($pos === false) {
            $alt = str_replace('-', '.', $iso);
            $pos = strpos($haystack, $alt);
            if ($pos === false) {
                // Lithuanian long-form dates are checked via year window only.
                return false;
            }
        }

        $window = mb_strtolower(mb_substr($haystack, max(0, $pos - 80), 160), 'UTF-8');

        foreach ([
            'nuoraš',
            'nuoras',
            'suformavo',
            'sertifikat',
            'galioja',
            'galiojimo',
            'nutarimu nr',
            'įsakymu nr',
            'isakymu nr',
            'company code',
            'parašas',
            'parasas',
            'patvirtintos',
            'patalpinta',
            'mano vmi',
        ] as $needle) {
            if (str_contains($window, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isPlausibleTicketDate(string $iso): bool
    {
        if (! preg_match('/^(20\d{2})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return false;
        }

        $ts = strtotime($iso);
        if ($ts === false) {
            return false;
        }

        $min = strtotime('-2 years');
        $max = strtotime('+2 days');

        return $ts >= $min && $ts <= $max;
    }

    private function lithuanianMonthDateToIso(string $year, string $monthWord, string $day): string
    {
        $map = [
            'sausio' => '01',
            'vasario' => '02',
            'kovo' => '03',
            'balandzio' => '04',
            'balandžio' => '04',
            'geguzes' => '05',
            'gegužės' => '05',
            'birzelio' => '06',
            'birželio' => '06',
            'liepos' => '07',
            'rugpjucio' => '08',
            'rugpjūčio' => '08',
            'rugsejo' => '09',
            'rugsėjo' => '09',
            'spalio' => '10',
            'lapkricio' => '11',
            'lapkričio' => '11',
            'gruodzio' => '12',
            'gruodžio' => '12',
        ];

        $key = mb_strtolower(trim($monthWord), 'UTF-8');
        // OCR often drops diacritics (lapkricio, geguzes).
        $ascii = strtr($key, [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
            'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        ]);
        $month = $map[$key] ?? $map[$ascii] ?? null;
        if ($month === null) {
            return '';
        }

        $dayNum = (int) $day;
        if ($dayNum < 1 || $dayNum > 31) {
            return '';
        }

        return sprintf('%s-%s-%02d', $year, $month, $dayNum);
    }
}
