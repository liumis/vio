<?php

namespace App\Support;

use App\Models\EmailSetting;
use RuntimeException;

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

            return array_values(array_filter(
                $normalized,
                fn (array $row): bool => ($row['ticket_date'] ?? null) === $wanted
            ));
        }

        return $this->filterByDateField($normalized, 'ticket_date', $dateFrom, $dateTo);
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

        return $this->filterRentalsOverlappingRange($normalized, $dateFrom, $dateTo);
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
     * Rentals whose check-out/check-in window covers the offense date (Y-m-d).
     *
     * @return list<array<string, mixed>>
     */
    public function findRentalsCoveringDate(string $vehicle, string $ticketDate): array
    {
        $wanted = substr(trim($ticketDate), 0, 10);
        if ($wanted === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $wanted)) {
            return [];
        }

        return array_values(array_filter(
            $this->searchRentalsByVehicle($vehicle),
            function (array $row) use ($wanted): bool {
                $from = substr((string) ($row['date_from'] ?? ''), 0, 10);
                $to = substr((string) ($row['date_to'] ?? ''), 0, 10);
                if ($from === '') {
                    return false;
                }
                if ($to === '') {
                    return $wanted >= $from;
                }

                return $wanted >= $from && $wanted <= $to;
            }
        ));
    }

    /**
     * Map a rental row into the same shape ReplyBuilder expects from traffic-violation rows.
     *
     * @param  array<string, mixed>  $rental
     * @return array<string, mixed>
     */
    public function rentalAsWheelsysDraft(array $rental, ?string $ticketDate = null, ?string $ticketDateTime = null): array
    {
        $driver = trim((string) ($rental['driver'] ?? ''));
        if ($driver === '') {
            $driver = trim((string) ($rental['corporate'] ?? ''));
        }

        return [
            'id' => $rental['id'] ?? null,
            'ticket_number' => null,
            'ticket_date' => $ticketDate,
            'ticket_datetime' => $ticketDateTime,
            'fine_received_date' => null,
            'vehicle' => $rental['vehicle'] ?? null,
            'agr_no' => $rental['agr_no'] ?? null,
            'driver' => $driver !== '' ? $driver : null,
            'customer_email' => $rental['customer_email'] ?? null,
            'driver_telephone' => $rental['driver_telephone'] ?? null,
            'driver_address' => $rental['driver_address'] ?? null,
            'driver_city' => $rental['driver_city'] ?? null,
            'driver_country' => $rental['driver_country'] ?? null,
            'birth_date' => $rental['birth_date'] ?? null,
            'driver_license' => $rental['driver_license'] ?? null,
            'licence_issue_date' => $rental['licence_issue_date'] ?? null,
            'licence_issue_place' => $rental['licence_issue_place'] ?? null,
            'authority_email' => null,
            'authority_name' => null,
            'agent' => $rental['agent'] ?? null,
            'corporate' => $rental['corporate'] ?? null,
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
     * Rental grid has no address — load it from Individual Renters (driverview).
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function enrichDraftWithDriver(array $draft): array
    {
        if (filled($draft['driver_address'] ?? null) && filled($draft['driver_city'] ?? null)) {
            return $draft;
        }

        $raw = is_array($draft['raw'] ?? null) ? $draft['raw'] : [];
        $driverId = $draft['driver_id'] ?? $raw['Driver_Id'] ?? null;
        $email = $draft['customer_email'] ?? $raw['DriverEmail'] ?? null;
        $name = $draft['driver'] ?? $raw['Driver_Name'] ?? null;

        $driver = $this->findDriver(
            driverId: $driverId !== null && $driverId !== '' ? (string) $driverId : null,
            email: filled($email) ? (string) $email : null,
            name: filled($name) ? (string) $name : null,
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

        return $draft;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDriver(?string $driverId = null, ?string $email = null, ?string $name = null): ?array
    {
        $this->login();
        $this->get($this->baseUrl().'/ui/manage/views/driverview.aspx');

        if (filled($email)) {
            $row = $this->searchDriverView('Email', (string) $email);
            if ($row !== null) {
                return $row;
            }
        }

        if (filled($name)) {
            $rows = $this->searchDriverViewRows('Name', (string) $name);
            if ($driverId !== null && $driverId !== '') {
                foreach ($rows as $candidate) {
                    if ((string) ($candidate['id'] ?? '') === (string) $driverId
                        || (string) ($candidate['code_id'] ?? '') === (string) $driverId) {
                        return $candidate;
                    }
                }
            }

            return $rows[0] ?? null;
        }

        return null;
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
        return [
            'id' => $row['Id'] ?? null,
            'code_id' => $row['Codeid'] ?? null,
            'name' => $row['Name'] ?? null,
            'address' => $row['Address'] ?? null,
            'city' => $row['City'] ?? null,
            'zip' => $row['ZipCode'] ?? null,
            'country' => $row['CountryName'] ?? null,
            'state' => $row['State'] ?? null,
            'email' => $row['Email'] ?? null,
            'phone' => $row['Telephone'] ?? $row['MobilePhone'] ?? null,
            'birth_date' => $this->dateOnly($row['BirthDate'] ?? null),
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
            throw new RuntimeException('Wheelsys credentials are not configured. Set them in Settings → System settings (Wheelsys) or WHEELSYS_* in .env.');
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
            'driver_telephone' => $row['drv_Phone1'] ?? null,
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
            'driver_telephone' => $row['DriverPhone'] ?? $row['DriverPhone2'] ?? null,
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

    private function dateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
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
            CURLOPT_TIMEOUT => 90,
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
}
