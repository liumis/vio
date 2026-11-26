<?php

namespace App\Support;

use App\Models\Authority;
use App\Models\FineProcessLog;
use Throwable;

/**
 * Whether an imported fine has everything needed to reply to the authority.
 */
final class FineReplyReadiness
{
    /** @var array<string, array{ready:bool,sent:bool,channel:string,missing:list<string>,label:string,detail:string}> */
    private static array $memo = [];

    /**
     * @return array{
     *     ready: bool,
     *     sent: bool,
     *     channel: 'email'|'epaslaugos'|'unknown',
     *     missing: list<string>,
     *     label: string,
     *     detail: string
     * }
     */
    public static function forLog(FineProcessLog $log, ?array $wheelsys = null): array
    {
        $memoKey = (string) ($log->id ?? 0).'|'.($wheelsys === null ? 'log' : 'w');
        if ($wheelsys === null && isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $sent = filled($log->email_sent_at);
        $missing = [];

        $wheelsys = is_array($wheelsys) ? $wheelsys : self::wheelsysFromLog($log);
        if (($wheelsys['driver'] ?? '') === '' && filled($log->driver_name)) {
            $wheelsys['driver'] = $log->driver_name;
        }
        if (($wheelsys['vehicle'] ?? '') === '' && filled($log->vehicle)) {
            $wheelsys['vehicle'] = $log->vehicle;
        }
        $party = (string) ($wheelsys['party'] ?? 'driver');

        if (! filled($log->vehicle) && ! filled($wheelsys['vehicle'] ?? null)) {
            $missing[] = 'vehicle';
        }
        $informeeName = trim((string) (
            $wheelsys['driver']
            ?? $wheelsys['corporate']
            ?? $log->driver_name
            ?? ''
        ));
        if ($informeeName === '') {
            $missing[] = $party === 'corporate' ? 'company' : 'driver';
        }
        if (! filled($log->reference)) {
            $missing[] = 'reference';
        }
        if (! $log->ticket_date) {
            $missing[] = 'ticket_date';
        }

        $authority = $log->relationLoaded('authority')
            ? $log->authority
            : ($log->authority_id ? Authority::query()->find($log->authority_id) : null);

        if (! $authority && filled($log->from_email)) {
            $authority = AuthorityMatcher::findForEmail((string) $log->from_email);
        }
        if (! $authority && filled($log->authority_name)) {
            $authority = Authority::query()
                ->where('name', $log->authority_name)
                ->first();
        }

        $channel = 'unknown';
        if ($authority) {
            $channel = str_contains(strtolower((string) $authority->email_pattern), 'anr.gov')
                ? 'epaslaugos'
                : 'email';
            $templateEarly = app(IncomingFineReplyBuilder::class)->resolveMailTemplate($authority, $party);
            if ($templateEarly === '' && $channel === 'email') {
                $missing[] = 'mail_template';
            }
        } else {
            $missing[] = 'authority';
        }

        // Same required template tokens as IncomingFineReplyBuilder::build().
        // Corporate informee (no physical Driver): birth date is not required.
        if ($authority) {
            $template = app(IncomingFineReplyBuilder::class)->resolveMailTemplate($authority, $party);
            if ($template !== '') {
                $companyCode = trim((string) (
                    $wheelsys['corporate_tax_id']
                    ?? $wheelsys['driver_tax_id']
                    ?? $wheelsys['company_code']
                    ?? $wheelsys['corporate_code']
                    ?? ''
                ));
                $checks = [
                    '[%2%]' => [$party === 'corporate' ? 'company' : 'driver', $informeeName],
                    '[%4%]' => ['vehicle', (string) ($wheelsys['vehicle'] ?? $log->vehicle ?? '')],
                    '[%8%]' => ['email', (string) ($wheelsys['customer_email'] ?? '')],
                ];
                if ($party !== 'corporate') {
                    $checks['[%7%]'] = ['birth_date', (string) ($wheelsys['birth_date'] ?? '')];
                } else {
                    $checks['[%13%]'] = ['company_code', $companyCode];
                }
                foreach ($checks as $token => [$label, $value]) {
                    if (str_contains($template, $token) && trim($value) === '' && ! in_array($label, $missing, true)) {
                        $missing[] = $label;
                    }
                }
            }
        }

        if ($channel === 'email' && $authority) {
            $to = app(IncomingFineReplyBuilder::class)->resolveReplyToEmail(
                authorityTo: (string) ($authority->to_email ?? ''),
                authorityMain: (string) ($authority->main_email ?? ''),
                authorityFrom: (string) ($authority->from_email ?? ''),
                senderFrom: (string) ($log->from_email ?? ''),
            );
            if ($to === '' || app(IncomingFineReplyBuilder::class)->isNoReplyEmail($to)) {
                if (! in_array('authority_to', $missing, true)) {
                    $missing[] = 'authority_to';
                }
            }
        }

        // Julianus (and flagged authorities): rental contract (RNT) must be tracked + fetchable.
        if ($authority && $authority->requiresContract()) {
            $agrNo = trim((string) ($wheelsys['agr_no'] ?? $log->agr_no ?? ''));
            $rentalId = $wheelsys['id'] ?? $log->rental_id ?? null;
            if (($agrNo === '' || ! filled($rentalId)) && ! in_array('contract', $missing, true)) {
                $missing[] = 'contract';
            }
        }

        $ready = $missing === [];

        if ($sent) {
            $result = [
                'ready' => true,
                'sent' => true,
                'channel' => $channel,
                'missing' => [],
                'label' => 'Sent',
                'detail' => 'Authority reply already sent'
                    .($log->email_sent_at ? ' · '.$log->email_sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : ''),
            ];
        } elseif ($channel === 'epaslaugos') {
            $result = [
                'ready' => $ready,
                'sent' => false,
                'channel' => $channel,
                'missing' => $missing,
                'label' => $ready ? 'Ready · ePaslaugos' : 'Missing',
                'detail' => $ready
                    ? 'Data OK — complete via IRD ePaslaugos (not email)'
                    : 'Missing: '.implode(', ', $missing),
            ];
        } else {
            $result = [
                'ready' => $ready,
                'sent' => false,
                'channel' => $channel,
                'missing' => $missing,
                'label' => $ready ? 'Ready' : 'Missing',
                'detail' => $ready
                    ? 'All required reply fields present'
                    : 'Missing: '.implode(', ', $missing),
            ];
        }

        if ($wheelsys === null) {
            self::$memo[$memoKey] = $result;
        }

        return $result;
    }

    /**
     * Human labels for missing field keys (modal warning banner).
     *
     * @param  list<string>  $missing
     * @return list<string>
     */
    public static function missingLabels(array $missing): array
    {
        $map = [
            'vehicle' => 'Vehicle / plate',
            'driver' => 'Driver name',
            'company' => 'Company name',
            'company_code' => 'Company code',
            'reference' => 'Reference / ticket no.',
            'ticket_date' => 'Ticket date',
            'birth_date' => 'Driver birth date',
            'email' => 'Email',
            'mail_template' => 'Authority mail template',
            'authority' => 'Authority match',
            'authority_to' => 'Authority To email',
            'contract' => 'Rental contract (RNT)',
        ];

        $out = [];
        foreach ($missing as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $out[] = $map[$key] ?? str_replace('_', ' ', $key);
        }

        return array_values(array_unique($out));
    }

    /**
     * Critical gaps block a safe send; others are warning-level.
     *
     * @param  list<string>  $missing
     */
    public static function missingSeverity(array $missing): string
    {
        if ($missing === []) {
            return 'ok';
        }

        $critical = ['vehicle', 'driver', 'company', 'reference', 'authority', 'authority_to', 'mail_template', 'ticket_date', 'contract'];
        foreach ($missing as $key) {
            if (in_array($key, $critical, true)) {
                return 'error';
            }
        }

        return 'warning';
    }

    /**
     * @return array<string, mixed>
     */
    public static function wheelsysFromLog(FineProcessLog $log): array
    {
        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $wheelsys = is_array($workflow['wheelsys'] ?? null) ? $workflow['wheelsys'] : [];

        return is_array($wheelsys) ? $wheelsys : [];
    }

    /**
     * Pull driver details from Wheelsys when possible and persist on the log workflow.
     *
     * @return array<string, mixed>
     */
    public static function enrichAndStore(FineProcessLog $log): array
    {
        $wheelsys = self::wheelsysFromLog($log);
        $wheelsys['vehicle'] = $wheelsys['vehicle'] ?? $log->vehicle;
        $wheelsys['driver'] = $wheelsys['driver'] ?? $log->driver_name;
        $wheelsys['ticket_number'] = $wheelsys['ticket_number'] ?? $log->reference;
        $wheelsys['ticket_date'] = $wheelsys['ticket_date']
            ?? ($log->ticket_date?->format('Y-m-d'));
        $wheelsys['agr_no'] = $wheelsys['agr_no'] ?? $log->agr_no;

        try {
            if (filled($log->rental_id) || filled($log->agr_no) || filled($log->vehicle)) {
                $wheelsys = app(WheelsysClient::class)->enrichDraftWithDriver($wheelsys);
            }
        } catch (Throwable) {
            // Keep whatever we already have.
        }

        $workflow = is_array($log->workflow) ? $log->workflow : [];
        $workflow['wheelsys'] = $wheelsys;
        $log->fill(['workflow' => $workflow])->save();

        // Drop memo so Reply bubble / open modal see fresh wheelsys fields.
        foreach (array_keys(self::$memo) as $key) {
            if (str_starts_with($key, (string) $log->id.'|')) {
                unset(self::$memo[$key]);
            }
        }

        return $wheelsys;
    }
}
