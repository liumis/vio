<?php

namespace App\Support;

/**
 * Assisted ROIK / IRD ePaslaugos fill (no public portal API).
 * Instruction: Administraciniai nusižengimai → Transporto priemonės savininkams → naudotojo teikimas.
 */
final class EpaslaugosAssist
{
    public const PORTAL_URL = 'https://epaslaugos.ird.lt/';

    public const PERSON_LT_NATURAL = 'lt_natural';

    public const PERSON_FOREIGN_NATURAL = 'foreign_natural';

    public const PERSON_LT_LEGAL = 'lt_legal';

    public const PERSON_FOREIGN_LEGAL = 'foreign_legal';

    /**
     * @return array<string, string>
     */
    public static function personTypeLabels(): array
    {
        return [
            self::PERSON_LT_NATURAL => 'Fizinis asmuo (LT)',
            self::PERSON_FOREIGN_NATURAL => 'Fizinis asmuo (užsienis)',
            self::PERSON_LT_LEGAL => 'Juridinis asmuo (LT)',
            self::PERSON_FOREIGN_LEGAL => 'Juridinis asmuo (užsienis)',
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>|null  $wheelsys
     * @return array{
     *     portal_url: string,
     *     rejected_url: string,
     *     roik: string,
     *     vehicle: string,
     *     ticket_date: string,
     *     person_type: string,
     *     person_type_labels: array<string, string>,
     *     fields: list<array{label:string,value:string}>,
     *     driver_block: string,
     *     checklist: list<string>,
     *     rejected_checklist: list<string>
     * }
     */
    public function build(array $draft, ?array $wheelsys = null, string $personType = self::PERSON_LT_NATURAL): array
    {
        if (! isset(self::personTypeLabels()[$personType])) {
            $personType = self::PERSON_LT_NATURAL;
        }

        $wheelsys = is_array($wheelsys) ? $wheelsys : [];
        $reference = trim((string) ($draft['reference'] ?? ''));
        $roik = $this->extractRoik($reference);

        $vehicle = strtoupper(trim((string) ($wheelsys['vehicle'] ?? $draft['vehicle_hint'] ?? '')));
        $ticketDate = trim((string) ($wheelsys['ticket_date'] ?? $draft['ticket_date_hint'] ?? ''));
        $driver = trim((string) ($wheelsys['driver'] ?? $wheelsys['corporate'] ?? ''));
        $birth = trim((string) ($wheelsys['birth_date'] ?? ''));
        $email = trim((string) ($wheelsys['customer_email'] ?? $wheelsys['email'] ?? $wheelsys['driver_email'] ?? ''));
        $license = trim((string) ($wheelsys['driver_license'] ?? ''));
        $companyCode = trim((string) ($wheelsys['company_code'] ?? $wheelsys['corporate_code'] ?? ''));
        $companyName = trim((string) ($wheelsys['corporate'] ?? $wheelsys['company_name'] ?? ''));
        $address = trim(implode(', ', array_filter([
            (string) ($wheelsys['driver_address'] ?? ''),
            (string) ($wheelsys['driver_city'] ?? ''),
            (string) ($wheelsys['driver_country'] ?? ''),
        ])));

        $fields = match ($personType) {
            self::PERSON_FOREIGN_NATURAL => array_values(array_filter([
                $this->field('Vardas, pavardė', $driver),
                $this->field('Gimimo data', $birth),
                $this->field('Pilnas adresas', $address),
                $this->field('El. paštas', $email),
            ])),
            self::PERSON_LT_LEGAL => array_values(array_filter([
                $this->field('Įmonės kodas', $companyCode !== '' ? $companyCode : $this->guessLithuanianCode($birth.$license.$driver)),
                $this->field('El. paštas', $email),
                $this->field('Įmonės pavadinimas (info)', $companyName !== '' ? $companyName : $driver),
            ])),
            self::PERSON_FOREIGN_LEGAL => array_values(array_filter([
                $this->field('Įmonės pavadinimas', $companyName !== '' ? $companyName : $driver),
                $this->field('Įmonės kodas', $companyCode),
                $this->field('Adresas', $address),
                $this->field('El. paštas', $email),
            ])),
            default => array_values(array_filter([
                $this->field('Vardas, pavardė', $driver),
                $this->field('Asmens kodas', $this->looksLikePersonalCode($birth) ? $birth : ''),
                $this->field('Gimimo data (jei nėra a.k.)', $this->looksLikePersonalCode($birth) ? '' : $birth),
                $this->field('El. paštas', $email),
            ])),
        };

        $driverLines = [];
        foreach ($fields as $field) {
            $driverLines[] = $field['label'].': '.$field['value'];
        }
        foreach ([
            $vehicle !== '' ? 'Valst. nr.: '.$vehicle : null,
            $ticketDate !== '' ? 'Pažeidimo data: '.$ticketDate : null,
            $roik !== '' ? 'ROIK: '.$roik : null,
            $license !== '' ? 'Vair. pažymėjimas: '.$license : null,
        ] as $extra) {
            if ($extra !== null) {
                $driverLines[] = $extra;
            }
        }

        return [
            'portal_url' => self::PORTAL_URL,
            'rejected_url' => self::PORTAL_URL,
            'roik' => $roik,
            'vehicle' => $vehicle,
            'ticket_date' => $ticketDate,
            'person_type' => $personType,
            'person_type_labels' => self::personTypeLabels(),
            'fields' => $fields,
            'driver_block' => implode("\n", $driverLines),
            'checklist' => [
                'Prisijungti juridinio asmens paskyra (eID / bankas)',
                'Meniu: Administraciniai nusižengimai → Transporto priemonės savininkams',
                'Paieška pagal ROIK (tiksliausia) arba valst. nr.',
                'Veiksmai → Transporto priemonės naudotojo teikimas',
                'Pasirinkti tipą: '.self::personTypeLabels()[$personType],
                'Įklijuoti laukus po vieną (Copy) → Pateikti → Taip → Taip',
            ],
            'rejected_checklist' => [
                'Periodiškai atidaryti ePaslaugas → Atmesti / Atmestus pranešimus',
                'Surasti pagal ROIK ir ištaisyti / pateikti iš naujo',
                'VIO: pažymėti „Atmesti checked“ šiame įraše',
            ],
        ];
    }

    public function extractRoik(string $reference): string
    {
        $reference = trim($reference);
        if (preg_match('/\bROIK\s*:?\s*([0-9]{6,})\b/iu', $reference, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(26\d{9})\b/', $reference, $m)) {
            return $m[1];
        }
        if (preg_match('/\b([0-9]{9,})\b/', $reference, $m)) {
            return $m[1];
        }

        return $reference;
    }

    /**
     * @return array{label:string,value:string}|null
     */
    private function field(string $label, string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return ['label' => $label, 'value' => $value];
    }

    private function looksLikePersonalCode(string $value): bool
    {
        return (bool) preg_match('/^\d{11}$/', trim($value));
    }

    private function guessLithuanianCode(string $haystack): string
    {
        if (preg_match('/\b(\d{9})\b/', $haystack, $m)) {
            return $m[1];
        }

        return '';
    }
}
