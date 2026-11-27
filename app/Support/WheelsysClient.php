<?php

namespace App\Support;

use App\Models\EmailSetting;
use RuntimeException;
use Throwable;

/**
 * Authenticated Wheelsys HTTP client for traffic-violation lookups.
 */
final class WheelsysClient
{
    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'wheelsys_');
    }

    public function __destruct()
    {
        if (is_string($this->cookieFile) && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    /**
     * Search traffic violations by plate, optionally narrowed by ticket date (Y-m-d).
     *
     * @return list<array<string, mixed>>
     */
    /**
     * @return list<array<string, mixed>>
     */
    public function searchTrafficViolations(
        string $vehicle,
        ?string $ticketDate = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $vehicle = strtoupper(trim($vehicle));
        try {
            if ($vehicle === '') {
                throw new RuntimeException('Vehicle number is required.');
            }

            $this->login();
            $this->get($this->baseUrl().'/ui/manage/views/trafficviolationview.aspx');

            $payload = [
                'searchField' => 'PLATENO',
                'searchValue' => $vehicle,
                'viewName' => 'trafficviolationview',
                'stations' => '',
                'status' => '',
                'mongoSupport' => false,
                'dataSkip' => 0,
                'dataSize' => 100,
                'searchUserId' => 0,
                'sortModel' => '[]',
                'dateStart' => '',
                'dateEnd' => '',
            ];

            $response = $this->postJson(
                $this->baseUrl().'/ui/manage/views/mainviewex.aspx/GetData',
                $payload,
                referer: $this->baseUrl().'/ui/manage/views/trafficviolationview.aspx',
            );

            $decoded = json_decode($response, true);
            $rows = json_decode($decoded['d']['data'] ?? 'null', true);
            if (! is_array($rows)) {
                throw new RuntimeException('Wheelsys returned an unexpected traffic-violation payload.');
            }

            $normalized = array_map(fn (array $row): array => $this->normalizeViolationRow($row), $rows);

            if ($ticketDate !== null && trim($ticketDate) !== '') {
                $wanted = substr(trim($ticketDate), 0, 10);

                $normalized = array_values(array_filter(
                    $normalized,
                    fn (array $row): bool => ($row['ticket_date'] ?? null) === $wanted
                ));
            } else {
                $normalized = $this->filterByDateField($normalized, 'ticket_date', $dateFrom, $dateTo);
            }

            $this->logIo('read traffic_violations', [
                'vehicle' => $vehicle,
                'ticket_date' => $ticketDate,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'count' => count($normalized),
            ]);

            return $normalized;
        } catch (Throwable $e) {
            $this->logIo('read traffic_violations', [
                'vehicle' => $vehicle,
                'ticket_date' => $ticketDate,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * Search rental agreements by vehicle plate.
     *
     * @return list<array<string, mixed>>
     */
    public function searchRentalsByVehicle(
        string $vehicle,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $vehicle = strtoupper(trim($vehicle));
        try {
            if ($vehicle === '') {
                throw new RuntimeException('Vehicle number is required.');
            }

            $this->login();
            $this->get($this->baseUrl().'/ui/manage/views/rentalview.aspx');

            $response = $this->postJson(
                $this->baseUrl().'/ui/manage/views/mainviewex.aspx/GetData',
                [
                    'searchField' => 'Plateno',
                    'searchValue' => $vehicle,
                    'viewName' => 'rentalview',
                    'stations' => '',
                    'status' => '',
                    'mongoSupport' => false,
                    'dataSkip' => 0,
                    'dataSize' => 100,
                    'searchUserId' => 0,
                    'sortModel' => '[]',
                    'dateStart' => '',
                    'dateEnd' => '',
                ],
                referer: $this->baseUrl().'/ui/manage/views/rentalview.aspx',
            );

            $decoded = json_decode($response, true);
            $rows = json_decode($decoded['d']['data'] ?? 'null', true);
            if (! is_array($rows)) {
                throw new RuntimeException('Wheelsys returned an unexpected rental payload.');
            }

            $normalized = array_map(fn (array $row): array => $this->normalizeRentalRow($row), $rows);
            $normalized = $this->filterRentalsOverlappingRange($normalized, $dateFrom, $dateTo);

            $this->logIo('read rentals', [
                'vehicle' => $vehicle,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'count' => count($normalized),
            ]);

            return $normalized;
        } catch (Throwable $e) {
            $this->logIo('read rentals', [
                'vehicle' => $vehicle,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterByDateField(array $rows, string $field, ?string $dateFrom, ?string $dateTo): array
    {
        $from = $this->dateOnly($dateFrom);
        $to = $this->dateOnly($dateTo);
        if ($from === null && $to === null) {
            return array_values($rows);
        }

        return array_values(array_filter(
            $rows,
            function (array $row) use ($field, $from, $to): bool {
                $value = $this->dateOnly($row[$field] ?? null);
                if ($value === null) {
                    return false;
                }
                if ($from !== null && $value < $from) {
                    return false;
                }
                if ($to !== null && $value > $to) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * Keep rentals that overlap [dateFrom, dateTo] (check-out / check-in window).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterRentalsOverlappingRange(array $rows, ?string $dateFrom, ?string $dateTo): array
    {
        $from = $this->dateOnly($dateFrom);
        $to = $this->dateOnly($dateTo);
        if ($from === null && $to === null) {
            return array_values($rows);
        }

        return array_values(array_filter(
            $rows,
            function (array $row) use ($from, $to): bool {
                $start = $this->dateOnly($row['date_from'] ?? null);
                $end = $this->dateOnly($row['date_to'] ?? null);
                if ($start === null) {
                    return false;
                }
                if ($end === null) {
                    $end = '9999-12-31';
                }
                if ($to !== null && $start > $to) {
                    return false;
                }
                if ($from !== null && $end < $from) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * Rentals whose check-out/check-in window covers the offense moment.
     * Prefer ticketDateTime (date+time); fall back to ticketDate (day-level).
     *
     * @return list<array<string, mixed>>
     */
    public function findRentalsCoveringDate(string $vehicle, string $ticketDate, ?string $ticketDateTime = null): array
    {
        $rawMoment = trim((string) ($ticketDateTime ?: $ticketDate));
        $wanted = $this->parseDateTime($rawMoment);
        if ($wanted === null) {
            return [];
        }
        $precise = $this->hasTimeComponent($rawMoment);

        return array_values(array_filter(
            $this->searchRentalsByVehicle($vehicle),
            function (array $row) use ($wanted, $precise): bool {
                $from = $this->parseDateTime($row['date_from'] ?? null);
                if ($from === null) {
                    return false;
                }
                $to = $this->parseDateTime($row['date_to'] ?? null);

                if (! $precise) {
                    $wantedDay = $wanted->toDateString();
                    $fromDay = $from->toDateString();
                    if ($to === null) {
                        return $wantedDay >= $fromDay;
                    }

                    return $wantedDay >= $fromDay && $wantedDay <= $to->toDateString();
                }

                if ($to === null) {
                    return $wanted->greaterThanOrEqualTo($from);
                }

                return $wanted->greaterThanOrEqualTo($from) && $wanted->lessThanOrEqualTo($to);
            }
        ));
    }

    /**
     * Human label for rental / ticket moments: "2026-10-28 07:30".
     */
    public static function formatDateTimeLabel(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        try {
            $dt = \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            return $raw;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $dt->format('Y-m-d');
        }

        return $dt->format('Y-m-d H:i');
    }

    /**
     * @return non-empty-string|null
     */
    public static function formatRentalWindow(mixed $from, mixed $to): ?string
    {
        $fromLabel = self::formatDateTimeLabel($from);
        $toLabel = self::formatDateTimeLabel($to);
        if ($fromLabel === '' && $toLabel === '') {
            return null;
        }
        if ($fromLabel !== '' && $toLabel !== '') {
            return $fromLabel.' ? '.$toLabel;
        }

        return $fromLabel !== '' ? $fromLabel : $toLabel;
    }

    /**
     * Map a rental row into the same shape ReplyBuilder expects from traffic-violation rows.
     *
     * Party rule (Imported fines / authority reply):
     * 1) physical person = Driver (never Corporate card while Driver exists)
     * 2) if no Driver ? Corporate, and address comes from Corporate
     *
     * @param  array<string, mixed>  $rental
     * @return array<string, mixed>
     */
    public function rentalAsWheelsysDraft(array $rental, ?string $ticketDate = null, ?string $ticketDateTime = null): array
    {
        $party = $this->resolveInformeeParty($rental);
        $informeeName = $party === 'corporate'
            ? trim((string) ($rental['corporate'] ?? ''))
            : trim((string) ($rental['driver'] ?? ''));

        return [
            'id' => $rental['id'] ?? null,
            'ticket_number' => null,
            'ticket_date' => $ticketDate,
            'ticket_datetime' => $ticketDateTime,
            'fine_received_date' => null,
            'vehicle' => $rental['vehicle'] ?? null,
            'agr_no' => $rental['agr_no'] ?? null,
            // Template [%2%]/ informee display name (driver or corporate).
            'driver' => $informeeName !== '' ? $informeeName : null,
            'customer_email' => $rental['customer_email'] ?? null,
            'driver_telephone' => $party === 'driver' ? ($rental['driver_telephone'] ?? null) : null,
            'driver_address' => $party === 'driver' ? ($rental['driver_address'] ?? null) : null,
            'driver_city' => $party === 'driver' ? ($rental['driver_city'] ?? null) : null,
            'driver_country' => $party === 'driver' ? ($rental['driver_country'] ?? null) : null,
            'birth_date' => $party === 'driver' ? ($rental['birth_date'] ?? null) : null,
            'driver_license' => $party === 'driver' ? ($rental['driver_license'] ?? null) : null,
            'licence_issue_date' => $party === 'driver' ? ($rental['licence_issue_date'] ?? null) : null,
            'licence_issue_place' => $party === 'driver' ? ($rental['licence_issue_place'] ?? null) : null,
            'authority_email' => null,
            'authority_name' => null,
            'agent' => $rental['agent'] ?? null,
            'corporate' => $rental['corporate'] ?? null,
            'party' => $party,
            'source' => 'rental',
            'rental_status' => $rental['status'] ?? null,
            'date_from' => $rental['date_from'] ?? null,
            'date_to' => $rental['date_to'] ?? null,
            'driver_id' => $rental['driver_id'] ?? null,
            'driver_code_id' => $rental['driver_code_id'] ?? null,
            'raw' => $rental['raw'] ?? $rental,
        ];
    }

    /**
     * Prefer physical Driver; only if missing use Corporate.
     *
     * @param  array<string, mixed>  $rentalOrDraft
     * @return 'driver'|'corporate'
     */
    public function resolveInformeeParty(array $rentalOrDraft): string
    {
        $raw = is_array($rentalOrDraft['raw'] ?? null) ? $rentalOrDraft['raw'] : [];
        $corporate = trim((string) (
            $rentalOrDraft['corporate']
            ?? $raw['Corporate_Name']
            ?? ''
        ));

        $driverId = $rentalOrDraft['driver_id'] ?? $raw['Driver_Id'] ?? null;
        $hasDriverId = filled($driverId) && (string) $driverId !== '0';
        // Physical person comes from Driver_* fields — not from Corporate card.
        $rawDriverName = trim((string) ($raw['Driver_Name'] ?? ''));
        if ($hasDriverId || $rawDriverName !== '') {
            return 'driver';
        }

        if ($corporate !== '') {
            return 'corporate';
        }

        // Legacy drafts may already have flattened corporate name into "driver".
        if (($rentalOrDraft['party'] ?? null) === 'corporate') {
            return 'corporate';
        }

        return 'driver';
    }

    /**
     * Load address / contacts for the informee party (Driver first, else Corporate).
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function enrichDraftWithDriver(array $draft): array
    {
        $party = $this->resolveInformeeParty($draft);
        $draft['party'] = $party;

        if ($party === 'corporate') {
            return $this->enrichDraftWithCorporate($draft);
        }

        $needsAddress = ! filled($draft['driver_address'] ?? null) || ! filled($draft['driver_city'] ?? null);
        $needsBirth = ! filled($draft['birth_date'] ?? null);
        $needsLicense = ! filled($draft['driver_license'] ?? null);
        $needsEmail = ! filled($draft['customer_email'] ?? null);
        $needsPhone = ! filled($draft['driver_telephone'] ?? null);
        $needsLicencePlace = ! filled($draft['licence_issue_place'] ?? null);

        // Still try Occupation ? asmens kodas ? birth date from raw rental/driver payload
        // even when we already have address (common Wheelsys gap).
        if (! $needsBirth) {
            // no-op — birth already present
        } elseif ($birth = $this->birthDateFromPersonalCodeSources($draft)) {
            $draft['birth_date'] = $birth;
            $needsBirth = false;
            $draft['_birth_from_personal_code'] = true;
        }

        if (! $needsAddress && ! $needsBirth && ! $needsLicense && ! $needsEmail && ! $needsPhone && ! $needsLicencePlace) {
            return $draft;
        }

        $raw = is_array($draft['raw'] ?? null) ? $draft['raw'] : [];
        $driverId = $draft['driver_id'] ?? $raw['Driver_Id'] ?? null;
        $email = $draft['customer_email'] ?? $raw['DriverEmail'] ?? null;
        // Physical person name from Driver_* — never Corporate_Name while party=driver.
        $name = trim((string) ($raw['Driver_Name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($draft['driver'] ?? ''));
            $corporate = trim((string) ($draft['corporate'] ?? $raw['Corporate_Name'] ?? ''));
            if ($corporate !== '' && strcasecmp($name, $corporate) === 0) {
                $name = '';
            }
        }

        $driver = $this->findDriver(
            driverId: $driverId !== null && $driverId !== '' ? (string) $driverId : null,
            email: filled($email) ? (string) $email : null,
            name: $name !== '' ? $name : null,
        );

        if ($driver === null) {
            return $draft;
        }

        $address = trim((string) ($driver['address'] ?? ''));
        $city = trim((string) ($driver['city'] ?? ''));
        $zip = trim((string) ($driver['zip'] ?? ''));
        $country = trim((string) ($driver['country'] ?? ''));

        if ($address !== '' && ! filled($draft['driver_address'] ?? null)) {
            $draft['driver_address'] = $address.($zip !== '' ? ', '.$zip : '');
        }
        if ($city !== '' && ! filled($draft['driver_city'] ?? null)) {
            $draft['driver_city'] = $city;
        }
        if ($country !== '' && ! filled($draft['driver_country'] ?? null)) {
            $draft['driver_country'] = $country;
        }
        // Licence issuing country often lives on driver record (LicIssuer).
        if (! filled($draft['licence_issue_place'] ?? null) && filled($driver['licence_issuer'] ?? null)) {
            $draft['licence_issue_place'] = $driver['licence_issuer'];
            if (! filled($draft['driver_country'] ?? null)) {
                $draft['driver_country'] = $driver['licence_issuer'];
            }
        }
        if (! filled($draft['birth_date'] ?? null) && filled($driver['birth_date'] ?? null)) {
            $draft['birth_date'] = $driver['birth_date'];
        }
        // Wheelsys often stores LT asmens kodas in Occupation (and sometimes IdNo / TaxId).
        if (! filled($draft['birth_date'] ?? null)) {
            $fromAk = $this->birthDateFromPersonalCodeSources($driver)
                ?? $this->birthDateFromPersonalCodeSources(array_merge($draft, ['raw' => $driver['raw'] ?? []]));
            if ($fromAk !== null) {
                $draft['birth_date'] = $fromAk;
                $draft['_birth_from_personal_code'] = true;
            }
        }
        if (! filled($draft['personal_code'] ?? null)) {
            $code = LithuanianPersonalCode::extract(
                $driver['occupation']
                ?? $driver['id_no']
                ?? $driver['tax_id']
                ?? ($driver['raw']['Occupation'] ?? null)
                ?? ($driver['raw']['IdNo'] ?? null)
                ?? ($driver['raw']['TaxId'] ?? null)
            );
            if ($code !== null) {
                $draft['personal_code'] = $code;
            }
        }
        if (! filled($draft['driver_license'] ?? null) && filled($driver['license'] ?? null)) {
            $draft['driver_license'] = $driver['license'];
        }
        if (! filled($draft['customer_email'] ?? null) && filled($driver['email'] ?? null)) {
            $draft['customer_email'] = $driver['email'];
        }
        if (! filled($draft['driver_telephone'] ?? null) && filled($driver['phone'] ?? null)) {
            $draft['driver_telephone'] = $driver['phone'];
        }

        $draft['_driver_enriched'] = true;
        $draft['party'] = 'driver';

        return $draft;
    }

    /**
     * Corporate Customers (corporateview) — used only when rental has no physical Driver.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function enrichDraftWithCorporate(array $draft): array
    {
        $raw = is_array($draft['raw'] ?? null) ? $draft['raw'] : [];
        $corporateName = trim((string) ($draft['corporate'] ?? $raw['Corporate_Name'] ?? ''));
        if ($corporateName === '') {
            $draft['party'] = 'corporate';

            // Name missing but code may exist ? try external JAR / rekvizitai fill.
            return $this->enrichCorporateFromExternalRegister($draft);
        }

        if (! filled($draft['driver'] ?? null)) {
            $draft['driver'] = $corporateName;
        }

        $needsAddress = ! filled($draft['driver_address'] ?? null) || ! filled($draft['driver_city'] ?? null);
        $needsEmail = ! filled($draft['customer_email'] ?? null);
        $needsPhone = ! filled($draft['driver_telephone'] ?? null);
        $needsTax = ! filled($draft['driver_tax_id'] ?? null) && ! filled($draft['corporate_tax_id'] ?? null);

        if (! $needsAddress && ! $needsEmail && ! $needsPhone && ! $needsTax) {
            $draft['party'] = 'corporate';
            $draft['_corporate_enriched'] = true;

            return $this->enrichCorporateFromExternalRegister($draft);
        }

        $corporate = $this->findCorporate(name: $corporateName);
        if ($corporate === null) {
            $draft['party'] = 'corporate';

            return $this->enrichCorporateFromExternalRegister($draft);
        }

        $address = trim((string) ($corporate['address'] ?? ''));
        $city = trim((string) ($corporate['city'] ?? ''));
        $zip = trim((string) ($corporate['zip'] ?? ''));
        $country = trim((string) ($corporate['country'] ?? ''));

        if ($address !== '' && ! filled($draft['driver_address'] ?? null)) {
            $draft['driver_address'] = $address.($zip !== '' ? ', '.$zip : '');
        }
        if ($city !== '' && ! filled($draft['driver_city'] ?? null)) {
            $draft['driver_city'] = $city;
        }
        if ($country !== '' && ! filled($draft['driver_country'] ?? null)) {
            $draft['driver_country'] = $country;
        }
        if (! filled($draft['customer_email'] ?? null) && filled($corporate['email'] ?? null)) {
            $draft['customer_email'] = $corporate['email'];
        }
        if (! filled($draft['driver_telephone'] ?? null) && filled($corporate['phone'] ?? null)) {
            $draft['driver_telephone'] = $corporate['phone'];
        }

        $taxId = trim((string) ($corporate['tax_id'] ?? $corporate['registration_no'] ?? ''));
        if ($taxId !== '') {
            $draft['corporate_tax_id'] = $taxId;
            if (! filled($draft['driver_tax_id'] ?? null)) {
                $draft['driver_tax_id'] = $taxId;
            }
        }

        // Juridical party — birth / licence stay empty on purpose.
        $draft['birth_date'] = null;
        $draft['driver_license'] = null;
        $draft['party'] = 'corporate';
        $draft['_corporate_enriched'] = true;
        $draft['corporate_id'] = $corporate['id'] ?? null;

        return $this->enrichCorporateFromExternalRegister($draft);
    }

    /**
     * When Wheelsys has company name XOR code, fill the missing side from rekvizitai / JAR.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function enrichCorporateFromExternalRegister(array $draft): array
    {
        $lookup = app(LithuanianCompanyLookup::class);
        $name = trim((string) ($draft['corporate'] ?? $draft['driver'] ?? ''));
        $code = $lookup->normalizeCompanyCode((string) (
            $draft['corporate_tax_id']
            ?? $draft['driver_tax_id']
            ?? $draft['company_code']
            ?? $draft['corporate_code']
            ?? ''
        ));

        $resolved = $lookup->resolve($name, $code);
        if ($resolved === null) {
            return $draft;
        }

        $draft['_external_company'] = $resolved;

        if (($resolved['status'] ?? '') !== 'filled' || ($resolved['filled'] ?? []) === []) {
            return $draft;
        }

        $resolvedName = trim((string) ($resolved['display_name'] ?? $resolved['name'] ?? ''));
        $resolvedCode = $lookup->normalizeCompanyCode((string) ($resolved['code'] ?? ''));

        if (in_array('company', $resolved['filled'], true) && $resolvedName !== '') {
            $draft['corporate'] = $resolvedName;
            $draft['driver'] = $resolvedName;
        }
        if (in_array('company_code', $resolved['filled'], true) && $resolvedCode !== '') {
            $draft['corporate_tax_id'] = $resolvedCode;
            $draft['company_code'] = $resolvedCode;
            if (! filled($draft['driver_tax_id'] ?? null)) {
                $draft['driver_tax_id'] = $resolvedCode;
            }
        }

        ActivityLogger::log(
            'Company data filled from external source'
                .($resolvedName !== '' ? ' · '.$resolvedName : '')
                .($resolvedCode !== '' ? ' · '.$resolvedCode : ''),
            meta: [
                'source' => $resolved['source'] ?? 'rekvizitai.lt / JAR',
                'filled' => $resolved['filled'],
                'query' => $resolved['query'] ?? null,
                'rekvizitai_url' => $resolved['rekvizitai_url'] ?? null,
            ]
        );

        return $draft;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCorporate(?string $name = null, ?string $taxId = null): ?array
    {
        try {
            $this->login();
            $this->get($this->baseUrl().'/ui/manage/views/corporateview.aspx');

            $row = null;
            if (filled($taxId)) {
                $row = $this->searchCorporateView('TaxId', (string) $taxId);
                if ($row === null) {
                    $row = $this->searchCorporateView('RegistrationNo', (string) $taxId);
                }
            }

            if ($row === null && filled($name)) {
                $rows = $this->searchCorporateViewRows('Name', (string) $name);
                $wanted = mb_strtolower(trim((string) $name));
                foreach ($rows as $candidate) {
                    if (mb_strtolower(trim((string) ($candidate['name'] ?? ''))) === $wanted) {
                        $row = $candidate;
                        break;
                    }
                }
                $row ??= $rows[0] ?? null;
            }

            $this->logIo('read corporate', [
                'name' => $name,
                'tax_id' => $taxId,
                'found' => $row !== null,
            ]);

            return $row;
        } catch (Throwable $e) {
            $this->logIo('read corporate', [
                'name' => $name,
                'tax_id' => $taxId,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function searchCorporateView(string $field, string $value): ?array
    {
        $rows = $this->searchCorporateViewRows($field, $value);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCorporateViewRows(string $field, string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $response = $this->postJson(
            $this->baseUrl().'/ui/manage/views/mainviewex.aspx/GetData',
            [
                'searchField' => $field,
                'searchValue' => $value,
                'viewName' => 'corporateview',
                'stations' => '',
                'status' => '',
                'mongoSupport' => false,
                'dataSkip' => 0,
                'dataSize' => 50,
                'searchUserId' => 0,
                'sortModel' => '[]',
                'dateStart' => '',
                'dateEnd' => '',
            ],
            referer: $this->baseUrl().'/ui/manage/views/corporateview.aspx',
        );

        $decoded = json_decode($response, true);
        $rows = json_decode($decoded['d']['data'] ?? 'null', true);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeCorporateRow($row), $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeCorporateRow(array $row): array
    {
        return [
            'id' => $row['Id'] ?? null,
            'code_id' => $row['CodeId'] ?? null,
            'name' => $row['Name'] ?? null,
            'tax_id' => $row['TaxId'] ?? null,
            'registration_no' => $row['RegistrationNo'] ?? null,
            'address' => $row['Address'] ?? null,
            'city' => $row['City'] ?? null,
            'zip' => $row['ZipCode'] ?? null,
            'country' => $row['CountryName'] ?? null,
            'email' => $row['Email'] ?? null,
            // Wheelsys Contact info has two telephone fields ? use whichever is filled.
            'phone' => $this->firstFilledPhone(
                $row['Telephone'] ?? null,
                $row['Telephone2'] ?? null,
                $row['MobilePhone'] ?? null,
                $row['Mobile'] ?? null,
                $row['Phone'] ?? null,
                $row['Phone2'] ?? null,
            ),
            'raw' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>  $sources
     */
    private function birthDateFromPersonalCodeSources(array $sources): ?string
    {
        $raw = is_array($sources['raw'] ?? null) ? $sources['raw'] : [];
        $candidates = [
            $sources['occupation'] ?? null,
            $sources['personal_code'] ?? null,
            $sources['id_no'] ?? null,
            $sources['tax_id'] ?? null,
            $sources['driver_id_number'] ?? null,
            $sources['driver_tax_id'] ?? null,
            $raw['Occupation'] ?? null,
            $raw['IdNo'] ?? null,
            $raw['TaxId'] ?? null,
            $raw['DrvIdNo'] ?? null,
            $raw['DrvTaxId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $birth = LithuanianPersonalCode::birthDateFrom($candidate);
            if ($birth !== null) {
                return $birth;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDriver(?string $driverId = null, ?string $email = null, ?string $name = null): ?array
    {
        try {
            $this->login();
            $this->get($this->baseUrl().'/ui/manage/views/driverview.aspx');

            $row = null;
            if (filled($email)) {
                $row = $this->searchDriverView('Email', (string) $email);
            }

            if ($row === null && filled($name)) {
                $rows = $this->searchDriverViewRows('Name', (string) $name);
                if ($driverId !== null && $driverId !== '') {
                    foreach ($rows as $candidate) {
                        if ((string) ($candidate['id'] ?? '') === (string) $driverId
                            || (string) ($candidate['code_id'] ?? '') === (string) $driverId) {
                            $row = $candidate;
                            break;
                        }
                    }
                }
                $row ??= $rows[0] ?? null;
            }

            $this->logIo('read driver', [
                'driver_id' => $driverId,
                'email' => $email,
                'name' => $name,
                'found' => $row !== null,
            ]);

            return $row;
        } catch (Throwable $e) {
            $this->logIo('read driver', [
                'driver_id' => $driverId,
                'email' => $email,
                'name' => $name,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function searchDriverView(string $field, string $value): ?array
    {
        $rows = $this->searchDriverViewRows($field, $value);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchDriverViewRows(string $field, string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $response = $this->postJson(
            $this->baseUrl().'/ui/manage/views/mainviewex.aspx/GetData',
            [
                'searchField' => $field,
                'searchValue' => $value,
                'viewName' => 'driverview',
                'stations' => '',
                'status' => '',
                'mongoSupport' => false,
                'dataSkip' => 0,
                'dataSize' => 50,
                'searchUserId' => 0,
                'sortModel' => '[]',
                'dateStart' => '',
                'dateEnd' => '',
            ],
            referer: $this->baseUrl().'/ui/manage/views/driverview.aspx',
        );

        $decoded = json_decode($response, true);
        $rows = json_decode($decoded['d']['data'] ?? 'null', true);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeDriverRow($row), $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeDriverRow(array $row): array
    {
        $occupation = trim((string) ($row['Occupation'] ?? ''));
        $idNo = trim((string) ($row['IdNo'] ?? ''));
        $taxId = trim((string) ($row['TaxId'] ?? ''));
        $birthDate = $this->dateOnly($row['BirthDate'] ?? null);
        if ($birthDate === null) {
            $birthDate = LithuanianPersonalCode::birthDateFrom($occupation)
                ?? LithuanianPersonalCode::birthDateFrom($idNo)
                ?? LithuanianPersonalCode::birthDateFrom($taxId);
        }

        return [
            'id' => $row['Id'] ?? null,
            'code_id' => $row['Codeid'] ?? null,
            'name' => $row['Name'] ?? null,
            'occupation' => $occupation !== '' ? $occupation : null,
            'id_no' => $idNo !== '' ? $idNo : null,
            'tax_id' => $taxId !== '' ? $taxId : null,
            'personal_code' => LithuanianPersonalCode::extract($occupation)
                ?? LithuanianPersonalCode::extract($idNo)
                ?? LithuanianPersonalCode::extract($taxId),
            'address' => $row['Address'] ?? null,
            'city' => $row['City'] ?? null,
            'zip' => $row['ZipCode'] ?? null,
            'country' => $row['CountryName'] ?? null,
            'state' => $row['State'] ?? null,
            'email' => $row['Email'] ?? null,
            // Wheelsys Contact info has two telephone fields ? use whichever is filled.
            'phone' => $this->firstFilledPhone(
                $row['Telephone'] ?? null,
                $row['Telephone2'] ?? null,
                $row['MobilePhone'] ?? null,
                $row['Mobile'] ?? null,
                $row['Phone'] ?? null,
                $row['Phone2'] ?? null,
            ),
            'birth_date' => $birthDate,
            'license' => $row['LicNo'] ?? null,
            'licence_issue_date' => $this->dateOnly($row['LicIssueDate'] ?? null),
            'licence_issuer' => $row['LicIssuer'] ?? null,
            'raw' => $row,
        ];
    }

    private function login(): void
    {
        $creds = EmailSetting::wheelsysConfig();
        $username = $creds['username'];
        $password = $creds['password'];
        if ($username === '' || $password === '') {
            throw new RuntimeException('Wheelsys credentials are not configured. Set them in Settings ? System settings (Wheelsys) or WHEELSYS_* in .env.');
        }

        $loginUrl = $this->baseUrl().'/sign-in/default.aspx?ReturnUrl='
            .rawurlencode('/ui/manage/views/trafficviolationview.aspx');

        $html = $this->get($loginUrl);
        if (! preg_match('/name="__VIEWSTATE"[^>]*value="([^"]*)"/', $html, $viewState)) {
            throw new RuntimeException('Could not read Wheelsys login form (__VIEWSTATE).');
        }
        if (! preg_match('/name="__VIEWSTATEGENERATOR"[^>]*value="([^"]*)"/', $html, $viewStateGenerator)) {
            throw new RuntimeException('Could not read Wheelsys login form (__VIEWSTATEGENERATOR).');
        }

        $this->postForm($loginUrl, [
            '__VIEWSTATE' => $viewState[1],
            '__VIEWSTATEGENERATOR' => $viewStateGenerator[1],
            'ctl00$coreBody$hdfPwd' => base64_encode($password),
            'ctl00$coreBody$hdfUpdatingPassword' => '0',
            'ctl00$coreBody$hdfEmail' => '',
            'ctl00$coreBody$FortNoxStateHidden' => '',
            'ctl00$coreBody$FortNoxStationHidden' => '',
            'tbEmail_text' => $username,
            'tbPassword_text' => '',
            'tbMFA_text' => '',
            'MFAUserId' => '',
            'hdConnectStation' => '',
            'hdConnectPos' => '',
            'ctl00$coreBody$btnActualSignin' => 'Sign-in',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeViolationRow(array $row): array
    {
        return [
            'id' => $row['Id'] ?? null,
            'ticket_number' => $row['TicketNo'] ?? null,
            'ticket_date' => $this->dateOnly($row['TicketDate'] ?? null),
            'ticket_datetime' => $row['TicketDate'] ?? null,
            'fine_received_date' => $this->dateOnly($row['FineReceivedDate'] ?? null),
            'vehicle' => $row['PLATENO'] ?? null,
            'agr_no' => $row['RADocNo'] ?? null,
            'driver' => $row['DriverNAME'] ?? null,
            'customer_email' => $row['Email'] ?? null,
            'driver_telephone' => $this->firstFilledPhone(
                $row['drv_Phone1'] ?? null,
                $row['drv_Phone2'] ?? null,
                $row['DriverPhone'] ?? null,
                $row['DriverPhone2'] ?? null,
            ),
            'driver_address' => $row['drv_Address'] ?? null,
            'driver_city' => $row['drv_City'] ?? null,
            'driver_country' => $row['drv_Country'] ?? null,
            'birth_date' => $this->dateOnly($row['BIRTHDATE'] ?? null),
            'driver_license' => $row['LicNo'] ?? null,
            'licence_issue_date' => $this->dateOnly($row['LicIssueDate'] ?? null),
            'licence_issue_place' => $row['LicIssuer'] ?? null,
            'authority_email' => $row['AuthorityEmail'] ?? $row['AuthorityName'] ?? null,
            'authority_name' => $row['AuthorityName'] ?? null,
            'agent' => $row['Agent'] ?? null,
            'corporate' => $row['Corporate'] ?? null,
            'driver_tax_id' => $row['DrvTaxId'] ?? null,
            'driver_id_number' => $row['DrvIdNo'] ?? null,
            'station' => $row['Branch_Name'] ?? null,
            'ticket_type' => $row['TicketType_Name'] ?? null,
            'charge' => $row['TicketFee'] ?? null,
            'admin_fee' => $row['AdminFee'] ?? null,
            // Traffic Violation lifecycle in Wheelsys (numeric State + PAIDDATE).
            'state' => isset($row['State']) ? (string) $row['State'] : null,
            'paid_date' => $this->dateOnly($row['PAIDDATE'] ?? null),
            'remaining_balance' => $row['RemainingBalance'] ?? null,
            'total_paid_amount' => $row['TotalPaidAmount'] ?? null,
            'raw' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRentalRow(array $row): array
    {
        $first = trim((string) ($row['DriverFirstName'] ?? ''));
        $last = trim((string) ($row['DriverLastName'] ?? ''));
        $driverName = trim((string) ($row['Driver_Name'] ?? ''));
        if ($driverName === '') {
            $driverName = trim($first.' '.$last);
        }

        return [
            'id' => $row['Id'] ?? null,
            'agr_no' => $row['DisplayDocNo'] ?? null,
            'vehicle' => $row['Plateno'] ?? null,
            'status' => $row['SStr'] ?? null,
            'date_from' => $row['DateFrom'] ?? null,
            'date_to' => $row['DateTo'] ?? null,
            'driver' => $driverName !== '' ? $driverName : null,
            'corporate' => $row['Corporate_Name'] ?? null,
            'agent' => $row['Agent_Name'] ?? null,
            'customer_email' => $row['DriverEmail'] ?? null,
            'driver_telephone' => $this->firstFilledPhone(
                $row['DriverPhone'] ?? null,
                $row['DriverPhone2'] ?? null,
                $row['drv_Phone1'] ?? null,
                $row['drv_Phone2'] ?? null,
            ),
            'driver_address' => null,
            'driver_city' => null,
            'driver_country' => null,
            'birth_date' => $this->dateOnly($row['DriverBirthDate'] ?? null),
            'driver_license' => $row['DriverLicNo'] ?? null,
            'licence_issue_date' => $this->dateOnly($row['DriverLicIssueDate'] ?? null),
            'licence_issue_place' => null,
            'car_model' => $row['CarModel'] ?? null,
            'driver_id' => $row['Driver_Id'] ?? null,
            'driver_code_id' => $row['DriverCodeID'] ?? null,
            'raw' => $row,
        ];
    }

    /**
     * List Dropzone attachments on a Rental (domain 5) master form.
     *
     * @return list<array{
     *     id: int|null,
     *     uid: string,
     *     file_name: string,
     *     original_file_name: string,
     *     file_size: int,
     *     entity_key: string,
     *     domain: int,
     *     uploaded_on: string|null,
     *     raw: array<string, mixed>
     * }>
     */
    public function listRentalAttachments(int|string $rentalEntityId): array
    {
        try {
            $meta = $this->rentalAttachmentsMeta($rentalEntityId);
            $this->logIo('read rental_attachment_list', [
                'rental_id' => $rentalEntityId,
                'count' => count($meta['files']),
            ]);

            return $meta['files'];
        } catch (Throwable $e) {
            $this->logIo('read rental_attachment_list', [
                'rental_id' => $rentalEntityId,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * Download all rental attachments as a ZIP (same as UI "Download all").
     */
    public function downloadRentalAttachmentsZip(int|string $rentalEntityId): string
    {
        try {
            $meta = $this->rentalAttachmentsMeta($rentalEntityId);
            if ($meta['files'] === []) {
                throw new RuntimeException('Rental '.$rentalEntityId.' has no attached documents.');
            }

            $filesPayload = array_map(static fn (array $f): array => $f['raw'], $meta['files']);
            $filename = 'allfiles_rental_'.$meta['pk'].'.zip';
            $body = http_build_query([
                'fileRequest' => 'zipall',
                'files' => json_encode($filesPayload, JSON_UNESCAPED_UNICODE),
                'filename' => $filename,
                'pk' => $meta['pk'],
                'domain' => $meta['domain'],
            ]);

            $zip = $this->request($this->baseUrl().'/attachments', $body, [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Origin: '.$this->baseUrl(),
                'Referer: '.$this->baseUrl().'/ui/manage/master/rental.aspx?entityId='.rawurlencode((string) $meta['pk']),
            ]);

            if (strlen($zip) < 4 || substr($zip, 0, 2) !== 'PK') {
                throw new RuntimeException('Wheelsys /attachments did not return a ZIP for rental '.$rentalEntityId.'.');
            }

            $this->logIo('read rental_attachments', [
                'rental_id' => $rentalEntityId,
                'files' => count($meta['files']),
                'bytes' => strlen($zip),
            ]);

            return $zip;
        } catch (Throwable $e) {
            $this->logIo('read rental_attachments', [
                'rental_id' => $rentalEntityId,
                'error' => $e->getMessage(),
            ], ok: false);

            throw $e;
        }
    }

    /**
     * Extract PDF (and other) files from the rental attachments ZIP.
     *
     * @return list<array{name: string, contents: string, bytes: int}>
     */
    public function downloadRentalAttachmentFiles(int|string $rentalEntityId): array
    {
        $zipBinary = $this->downloadRentalAttachmentsZip($rentalEntityId);
        $tmp = tempnam(sys_get_temp_dir(), 'wheels_rnt_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file for Wheelsys ZIP.');
        }
        $zipPath = $tmp.'.zip';
        @unlink($tmp);
        file_put_contents($zipPath, $zipBinary);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('Could not open Wheelsys attachments ZIP.');
        }

        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || $name === '' || str_ends_with($name, '/')) {
                continue;
            }
            $base = basename(str_replace('\\', '/', $name));
            if ($base === '' || str_starts_with($base, '.')) {
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if (! is_string($contents) || $contents === '') {
                continue;
            }
            $out[] = [
                'name' => $base,
                'contents' => $contents,
                'bytes' => strlen($contents),
            ];
        }
        $zip->close();
        @unlink($zipPath);

        return $out;
    }

    /**
     * @return array{
     *     pk: string,
     *     domain: string,
     *     files: list<array{
     *         id: int|null,
     *         uid: string,
     *         file_name: string,
     *         original_file_name: string,
     *         file_size: int,
     *         entity_key: string,
     *         domain: int,
     *         uploaded_on: string|null,
     *         raw: array<string, mixed>
     *     }>
     * }
     */
    private function rentalAttachmentsMeta(int|string $rentalEntityId): array
    {
        $pk = trim((string) $rentalEntityId);
        if ($pk === '' || ! ctype_digit($pk)) {
            throw new RuntimeException('Rental entity id is required.');
        }

        $this->login();
        $html = $this->get($this->baseUrl().'/ui/manage/master/rental.aspx?entityId='.rawurlencode($pk));

        $domain = '5';
        if (preg_match('/wheels\.Domain\s*=\s*[\'"](\d+)[\'"]/', $html, $m)) {
            $domain = $m[1];
        }

        $filesJson = null;
        if (preg_match('/id="formAttachments_hidden"[^>]*value="([^"]*)"/', $html, $m)) {
            $filesJson = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (preg_match('/name="formAttachments\$formAttachments_hidden"[^>]*value="([^"]*)"/', $html, $m)) {
            $filesJson = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $rawFiles = is_string($filesJson) && $filesJson !== ''
            ? json_decode($filesJson, true)
            : [];
        if (! is_array($rawFiles)) {
            $rawFiles = [];
        }

        $files = [];
        foreach ($rawFiles as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['IsDeleted'])) {
                continue;
            }
            $fileName = trim((string) ($row['FileName'] ?? $row['OriginalFileName'] ?? ''));
            $uid = trim((string) ($row['Uid'] ?? ''));
            if ($fileName === '' || $uid === '') {
                continue;
            }
            $files[] = [
                'id' => isset($row['Id']) ? (int) $row['Id'] : null,
                'uid' => $uid,
                'file_name' => $fileName,
                'original_file_name' => trim((string) ($row['OriginalFileName'] ?? $fileName)),
                'file_size' => (int) ($row['FileSize'] ?? 0),
                'entity_key' => trim((string) ($row['EntityKey'] ?? $pk)),
                'domain' => (int) ($row['Domain'] ?? $domain),
                'uploaded_on' => isset($row['UploadedOn']) ? (string) $row['UploadedOn'] : null,
                'raw' => $row,
            ];
        }

        return [
            'pk' => $pk,
            'domain' => $domain,
            'files' => $files,
        ];
    }

    private function dateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }

    /**
     * Wheelsys often has two contact telephone inputs; pick the first non-empty.
     */
    private function firstFilledPhone(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $phone = trim((string) ($candidate ?? ''));
            if ($phone !== '') {
                return $phone;
            }
        }

        return null;
    }

    private function hasTimeComponent(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return (bool) preg_match('/\d{1,2}:\d{2}/', $value);
    }

    private function parseDateTime(mixed $value): ?\Illuminate\Support\Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            // Contracts / offense times are minute-precision — ignore seconds.
            return \Illuminate\Support\Carbon::parse($raw)->startOfMinute();
        } catch (\Throwable) {
            return null;
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.wheelsys.base_url', 'https://lt.wheelsys.greenmotion.com'), '/');
    }

    private function get(string $url): string
    {
        return $this->request($url);
    }

    /**
     * @param  array<string, scalar|null>  $fields
     */
    private function postForm(string $url, array $fields): string
    {
        return $this->request($url, http_build_query($fields), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postJson(string $url, array $payload, string $referer): string
    {
        return $this->request($url, json_encode($payload, JSON_UNESCAPED_UNICODE), [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/json; charset=utf-8',
            'X-Requested-With: XMLHttpRequest',
            'Origin: '.$this->baseUrl(),
            'Referer: '.$referer,
        ]);
    }

    /**
     * @param  list<string>  $headers
     */
    private function request(string $url, ?string $body = null, array $headers = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => HttpTimeouts::long(),
            CURLOPT_HTTPHEADER => array_merge(['User-Agent: Mozilla/5.0 (compatible; SitGo-VIO/1.0)'], $headers),
            CURLOPT_POST => $body !== null,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            throw new RuntimeException(
                'Wheelsys request failed'
                .($error !== '' ? ": {$error}" : " (HTTP {$status}).")
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logIo(string $op, array $meta = [], bool $ok = true): void
    {
        try {
            ActivityLogger::log(
                ($ok ? 'Wheelsys: ' : 'Wheelsys failed: ').$op,
                meta: array_merge(['ok' => $ok, 'system' => 'wheelsys'], $meta),
            );
        } catch (Throwable) {
            // Never break Wheelsys IO because of logging.
        }
    }
}
