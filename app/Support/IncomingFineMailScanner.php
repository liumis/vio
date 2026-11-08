<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\FineProcessLog;

/**
 * Picks inbox messages that look like traffic/parking fines and maps sender → Authority.
 */
final class IncomingFineMailScanner
{
    /**
     * Subject keywords used when sender is not yet in Authorities.
     *
     * @var list<string>
     */
    private const SUBJECT_KEYWORDS = [
        'bauda',
        'baudą',
        'baudos',
        'baudų',
        'parkavimo',
        'parkavimas',
        'parking fine',
        'parking ticket',
        'greičio',
        'speed camera',
        'traffic fine',
        'traffic violation',
        'administracinio nusižengimo',
        'administracinis nusižengimas',
        'policij',
        'savivaldyb',
        'nutarim',
        'protokol',
        'eismo taisykli',
        'kelio eismo',
    ];

    /**
     * Internal / vendor domains that should never count as fine senders via keywords alone.
     *
     * @var list<string>
     */
    private const IGNORE_SENDER_DOMAINS = [
        'greenmotion.com',
        'docs.greenmotion.com',
        'greenmotion.lt',
        'greenlease.lt',
        'greenlease.com',
        'sitandgo.lt',
        'microsoft.com',
        'office365.com',
    ];

    /**
     * Legal Balance debt-reminder subjects/bodies (ignore these only — keep other LB mail).
     * Prefer ASCII / folded forms — matched against diacritic-folded haystack.
     *
     * @var list<string>
     */
    private const LEGAL_BALANCE_DEBT_PHRASES = [
        'raginimas pasidengti',
        'pasidengti skol',
        'skolu detalizacija',
        'skolos detalizacija',
        'skolos isieskojim',
        'pradeta skolos',
        'pradetas skolos',
        'pranesimas apie pradeta',
        'pradeta skolos isieskojim',
        'perduota isieskojimui',
        'skola perduota',
        'bylos numeris',
        'sutarties priedo derinimas',
        'isieskojimo efektyvum',
        'imoku surinkimo',
        'debt collection',
        'cover the debt',
        'outstanding debt',
        'debt recovery',
    ];

    /**
     * Off-topic notices even from Authority domains — not driver/fine processing.
     * Prefer ASCII / folded forms — matched against diacritic-folded haystack.
     *
     * @var list<string>
     */
    private const NON_FINE_PHRASES = [
        // Move car / roadworks
        'perstatyti',
        'perstatyk',
        'perstatyti automobil',
        'trukdo atliekamiems darbams',
        'kelio remonto',
        'remonto darbai',
        'prasome perstatyti',
        'please move',
        'move the vehicle',
        'move your vehicle',
        'blocking roadworks',
        'road works',
        'roadworks',
        // Payment receipts / confirmations (not new fines)
        'apmokejimas gautas',
        'mokejimas gautas',
        'mokejimas patvirtintas',
        'mokejimo patvirtinimas',
        'apmokejimo patvirtinimas',
        'pinigai gauti',
        'bauda sumoketa',
        'bauda apmoketa',
        'sekmingai apmoketa',
        'sekmingai sumoketa',
        'dekui uz mokejima',
        'dekui uz apmokejima',
        'payment received',
        'payment confirmation',
        'payment confirmed',
        'thank you for your payment',
        'we have received your payment',
        'receipt of payment',
    ];

    public function __construct(
        private readonly MicrosoftGraphInbox $inbox = new MicrosoftGraphInbox,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     subject: string,
     *     from_email: string,
     *     from_name: string,
     *     received_at: string,
     *     received_at_local: string,
     *     preview: string,
     *     has_attachments: bool,
     *     authority_id: int|null,
     *     authority_name: string,
     *     match_reason: string
     * }>
     */
    public function scan(int $days = 3, int $top = 0): array
    {
        // Longer look-backs need a larger Graph page budget (paginated in MicrosoftGraphInbox).
        if ($top <= 0) {
            $top = $days >= 60 ? 1500 : ($days >= 30 ? 800 : 200);
        }

        // Inbox only — already-handled fines stay visible via VIO / import status chips.
        $messages = $this->inbox->listInboxMessages(days: $days, top: $top);

        // Graph folder $filter frequently caps ~100 without nextLink — backfill via domain search.
        if ($days >= 14) {
            $domains = Authority::query()
                ->whereNotNull('email_pattern')
                ->where('email_pattern', '!=', '')
                ->pluck('email_pattern')
                ->map(fn ($p) => ltrim(strtolower((string) $p), '@'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $extra = $this->inbox->searchMessagesFromDomains($domains, $days, 25);
            if ($extra !== []) {
                $seen = array_fill_keys(array_column($messages, 'id'), true);
                foreach ($extra as $row) {
                    $id = (string) ($row['id'] ?? '');
                    if ($id === '' || isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $messages[] = $row;
                }
                usort($messages, static function (array $a, array $b): int {
                    return strcmp((string) ($b['received_at'] ?? ''), (string) ($a['received_at'] ?? ''));
                });
            }
        }

        // Do NOT hide messages already in fine_process_logs — Incoming Fetch
        // enriches them and shows In VIO / Wheelsys import status instead.
        $results = [];

        foreach ($messages as $message) {
            if ($this->isNonFineNotice(
                (string) ($message['subject'] ?? ''),
                (string) ($message['preview'] ?? '')
            )) {
                continue;
            }

            $ignoredSender = $this->isIgnoredSender($message['from_email']);
            $subjectHit = $this->looksLikeFine($message['subject']);

            // Never treat internal/vendor domains as the issuing Authority.
            $authority = (! $ignoredSender && $message['from_email'] !== '')
                ? AuthorityMatcher::findForEmail($message['from_email'])
                : null;

            // Forwards / internal relays: guess Authority from subject when sender domain is unknown.
            $subjectAuthority = null;
            if ($authority === null && $subjectHit) {
                $subjectAuthority = AuthorityMatcher::findFromHaystack((string) $message['subject']);
                if ($subjectAuthority !== null) {
                    $authority = $subjectAuthority;
                }
            }

            // Legal Balance: skip debt reminders only, not every letter from that domain.
            if ($this->isLegalBalanceDebtReminder(
                (string) ($message['from_email'] ?? ''),
                (string) ($message['subject'] ?? ''),
                (string) ($message['preview'] ?? ''),
                $authority
            )) {
                continue;
            }

            // Authority match always wins; keyword match for unknown / forwarded fine notices.
            if ($authority === null && ! $subjectHit) {
                continue;
            }

            // Internal forwards without attachments cannot be matched to an issuing authority.
            if ($ignoredSender && $authority === null && empty($message['has_attachments'])) {
                continue;
            }

            $reasons = [];
            if ($authority !== null && $subjectAuthority === null && ! $ignoredSender) {
                $reasons[] = 'sender';
            }
            if ($subjectAuthority !== null) {
                $reasons[] = 'subject-authority';
            }
            if ($subjectHit) {
                $reasons[] = 'subject';
            }

            $receivedAt = $message['received_at'];
            $local = $receivedAt !== ''
                ? \Illuminate\Support\Carbon::parse($receivedAt)->timezone(config('app.timezone'))->format('Y-m-d H:i')
                : '';

            $results[] = [
                ...$message,
                'received_at_local' => $local,
                'authority_id' => $authority?->id,
                'authority_name' => $authority?->name ?? '—',
                // ANR/ROIK: portal submit (ePaslaugos), not email reply.
                'reply_channel' => str_contains(strtolower((string) ($authority?->email_pattern ?? '')), 'anr.gov')
                    ? 'epaslaugos'
                    : 'email',
                'match_reason' => implode('+', $reasons),
            ];
        }

        return $results;
    }

    private function looksLikeFine(string $subject): bool
    {
        $haystack = mb_strtolower(trim($subject));

        if ($haystack === '') {
            return false;
        }

        foreach (self::SUBJECT_KEYWORDS as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Skip move-car / payment-receipt / other operational mails (even from Authority domains).
     */
    private function isNonFineNotice(string $subject, string $preview): bool
    {
        $haystack = mb_strtolower(trim($subject."\n".$preview), 'UTF-8');
        if ($haystack === '') {
            return false;
        }

        // ASCII-fold common LT diacritics so OCR/email variants still match.
        $folded = strtr($haystack, [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
            'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        ]);

        foreach (self::NON_FINE_PHRASES as $phrase) {
            $needle = strtr(mb_strtolower($phrase, 'UTF-8'), [
                'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
                'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
            ]);
            if ($needle !== '' && str_contains($folded, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isIgnoredSender(string $fromEmail): bool
    {
        $domain = AuthorityMatcher::domainOf($fromEmail);
        if ($domain === '') {
            return false;
        }

        foreach (self::IGNORE_SENDER_DOMAINS as $ignored) {
            if ($domain === $ignored || str_ends_with($domain, '.'.$ignored)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \App\Models\Authority|null  $authority
     */
    private function isLegalBalanceDebtReminder(
        string $fromEmail,
        string $subject,
        string $preview,
        $authority
    ): bool {
        $domain = AuthorityMatcher::domainOf($fromEmail);
        $isLegalBalance = $domain === 'legalbalance.lt'
            || str_ends_with($domain, '.legalbalance.lt')
            || ($authority !== null && str_contains(strtolower((string) ($authority->email_pattern ?? '')), 'legalbalance.lt'));

        if (! $isLegalBalance) {
            return false;
        }

        $haystack = mb_strtolower(trim($subject."\n".$preview), 'UTF-8');
        $fold = static function (string $value): string {
            return strtr(mb_strtolower($value, 'UTF-8'), [
                'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
                'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
            ]);
        };
        $folded = $fold($haystack);

        foreach (self::LEGAL_BALANCE_DEBT_PHRASES as $phrase) {
            $needle = $fold($phrase);
            if ($needle !== '' && str_contains($folded, $needle)) {
                return true;
            }
        }

        return false;
    }
}
