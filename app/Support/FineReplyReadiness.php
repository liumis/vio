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

        if (! filled($log->vehicle)) {
            $missing[] = 'vehicle';
        }
        if (! filled($log->driver_name)) {
            $missing[] = 'driver';
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
            if (! filled($authority->mail_template) && $channel === 'email') {
                $missing[] = 'mail_template';
            }
        } else {
            $missing[] = 'authority';
        }

        $wheelsys = is_array($wheelsys) ? $wheelsys : self::wheelsysFromLog($log);
        if (($wheelsys['driver'] ?? '') === '' && filled($log->driver_name)) {
            $wheelsys['driver'] = $log->driver_name;
        }
        if (($wheelsys['vehicle'] ?? '') === '' && filled($log->vehicle)) {
            $wheelsys['vehicle'] = $log->vehicle;
        }

        // Same required template tokens as IncomingFineReplyBuilder::build().
        if ($authority && filled($authority->mail_template)) {
            $template = (string) $authority->mail_template;
            foreach ([
                '[%2%]' => ['driver', (string) ($wheelsys['driver'] ?? $log->driver_name ?? '')],
                '[%4%]' => ['vehicle', (string) ($wheelsys['vehicle'] ?? $log->vehicle ?? '')],
                '[%7%]' => ['birth_date', (string) ($wheelsys['birth_date'] ?? '')],
                '[%8%]' => ['email', (string) ($wheelsys['customer_email'] ?? '')],
            ] as $token => [$label, $value]) {
                if (str_contains($template, $token) && trim($value) === '' && ! in_array($label, $missing, true)) {
                    $missing[] = $label;
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

        return $wheelsys;
    }
}
