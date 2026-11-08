<?php

namespace App\Http\Controllers;

use App\Support\WheelsysLoginBridge;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class WheelsysSsoController extends Controller
{
    public function open(Request $request): Response
    {
        $returnUrl = $request->query('return');

        try {
            $payload = WheelsysLoginBridge::buildFormPayload(
                is_string($returnUrl) ? $returnUrl : null
            );
        } catch (Throwable $e) {
            return response(
                view('wheelsys.sso-error', ['message' => $e->getMessage()]),
                500
            );
        }

        return response()->view('wheelsys.sso-redirect', [
            'action' => $payload['action'],
            'fields' => $payload['fields'],
        ]);
    }

    public function createTrafficViolation(Request $request): Response
    {
        $draft = $request->session()->get('wheelsys_create_draft');

        if (! is_array($draft) || $draft === []) {
            return response(
                view('wheelsys.sso-error', [
                    'message' => 'No prepared traffic-violation data found. Go back to Manual Wheelsys search and click Create in Wheels again.',
                ]),
                400
            );
        }

        $openUrl = route('wheelsys.sso', [
            'return' => WheelsysLoginBridge::NEW_TRAFFIC_VIOLATION_PATH,
        ]);

        return response()->view('wheelsys.create-traffic-violation', [
            'draft' => $draft,
            'openUrl' => $openUrl,
            'fields' => self::prefillFields($draft),
        ]);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<array{label: string, value: string, hint: string}>
     */
    private static function prefillFields(array $draft): array
    {
        $ticketDate = (string) ($draft['ticket_date'] ?? '');
        $ticketDateTime = (string) ($draft['ticket_datetime'] ?? '');
        $time = '';
        if (str_contains($ticketDateTime, 'T')) {
            $time = substr($ticketDateTime, 11, 5);
        }

        return [
            [
                'label' => 'Vehicle',
                'value' => (string) ($draft['vehicle'] ?? ''),
                'hint' => 'Wheels field: Vehicle',
            ],
            [
                'label' => 'Ticket date',
                'value' => $ticketDate,
                'hint' => 'Wheels field: Date',
            ],
            [
                'label' => 'Ticket time',
                'value' => $time,
                'hint' => 'Wheels field: Time (needed for rental lookup)',
            ],
            [
                'label' => 'Reference no',
                'value' => (string) ($draft['ticket_number'] ?? ''),
                'hint' => 'Wheels field: Reference no',
            ],
            [
                'label' => 'Authority email / name',
                'value' => (string) ($draft['authority_email'] ?? $draft['authority_name'] ?? ''),
                'hint' => 'Wheels field: Authority',
            ],
            [
                'label' => 'Fine received date',
                'value' => (string) ($draft['fine_received_date'] ?? ''),
                'hint' => 'Wheels field: Fine received date',
            ],
            [
                'label' => 'Driver (expected after lookup)',
                'value' => (string) ($draft['driver'] ?? ''),
                'hint' => 'Should auto-fill via GetRentalInfo after Vehicle + Date/Time',
            ],
            [
                'label' => 'RNT (expected after lookup)',
                'value' => (string) ($draft['agr_no'] ?? ''),
                'hint' => 'Should auto-fill via GetRentalInfo',
            ],
        ];
    }
}
