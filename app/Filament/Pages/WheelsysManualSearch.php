<?php

namespace App\Filament\Pages;

use App\Support\ActivityLogger;
use App\Support\WheelsysClient;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class WheelsysManualSearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Manual search';

    protected static ?string $navigationGroup = 'Fines';

    protected static ?string $title = 'Manual search';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.wheelsys-manual-search';

    public ?array $data = [];

    /** @var 'fines'|'contracts'|null */
    public ?string $resultMode = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $results = [];

    public ?array $selected = null;

    public bool $showDetails = false;

    /** @var array<string, mixed>|null */
    public ?array $detailsRow = null;

    public function mount(): void
    {
        $this->form->fill([
            'vehicle' => '',
            'date_from' => now()->subMonths(6)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('vehicle')
                    ->label('Vehicle number')
                    ->placeholder('e.g. MAB841')
                    ->required()
                    ->maxLength(32)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\DatePicker::make('date_from')
                    ->label('From')
                    ->native(false)
                    ->displayFormat('Y-m-d')
                    ->default(now()->subMonths(6))
                    ->required(),
                Forms\Components\DatePicker::make('date_to')
                    ->label('To')
                    ->native(false)
                    ->displayFormat('Y-m-d')
                    ->default(now())
                    ->required()
                    ->helperText('Default range: last 6 months.'),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function findFines(): void
    {
        $this->runSearch('fines');
    }

    public function findContracts(): void
    {
        $this->runSearch('contracts');
    }

    /**
     * @param  'fines'|'contracts'  $mode
     */
    private function runSearch(string $mode): void
    {
        [$vehicle, $dateFrom, $dateTo] = $this->searchInputs();
        if ($vehicle === null) {
            return;
        }

        $this->selected = null;
        $this->closeDetails();
        $this->resultMode = $mode;

        try {
            $client = app(WheelsysClient::class);
            $this->results = $mode === 'fines'
                ? $client->searchTrafficViolations($vehicle, null, $dateFrom, $dateTo)
                : $client->searchRentalsByVehicle($vehicle, $dateFrom, $dateTo);
        } catch (Throwable $e) {
            $this->results = [];
            $this->logManualSearchAccess('search', [
                'mode' => $mode,
                'vehicle' => $vehicle,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'result_count' => 0,
                'outcome' => 'error',
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title($mode === 'fines' ? 'Find fines failed' : 'Find contracts failed')
                ->body($e->getMessage())
                ->send();

            return;
        }

        $label = $mode === 'fines' ? 'fine(s)' : 'contract(s)';
        $count = count($this->results);

        $this->logManualSearchAccess('search', [
            'mode' => $mode,
            'vehicle' => $vehicle,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'result_count' => $count,
            'outcome' => $count === 0 ? 'empty' : 'ok',
        ]);

        if ($count === 0) {
            Notification::make()
                ->warning()
                ->title('Nothing found')
                ->body('No '.$label.' for '.$vehicle.' in '.$dateFrom.' … '.$dateTo.'.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Search complete')
            ->body($count.' '.$label.' found.')
            ->send();
    }

    /**
     * @return array{0: ?string, 1: string, 2: string}
     */
    private function searchInputs(): array
    {
        $state = $this->form->getState();
        $vehicle = strtoupper(trim((string) ($state['vehicle'] ?? '')));
        $dateFrom = substr((string) ($state['date_from'] ?? ''), 0, 10);
        $dateTo = substr((string) ($state['date_to'] ?? ''), 0, 10);

        if ($vehicle === '') {
            Notification::make()->warning()->title('Enter a vehicle number')->send();

            return [null, '', ''];
        }

        if ($dateFrom === '' || $dateTo === '') {
            Notification::make()->warning()->title('Set both From and To dates')->send();

            return [null, '', ''];
        }

        if ($dateFrom > $dateTo) {
            Notification::make()->warning()->title('From date must be before To date')->send();

            return [null, '', ''];
        }

        return [$vehicle, $dateFrom, $dateTo];
    }

    public function openDetails(int $index): void
    {
        $row = $this->results[$index] ?? null;
        if ($row === null) {
            return;
        }

        $this->detailsRow = $row;
        $this->showDetails = true;

        $this->logManualSearchAccess('view_details', [
            'mode' => $this->resultMode,
            'vehicle' => $row['vehicle'] ?? null,
            'wheels_id' => isset($row['id']) ? (string) $row['id'] : null,
            'agr_no' => $row['agr_no'] ?? null,
            'ticket_number' => $row['ticket_number'] ?? null,
            'ticket_date' => $row['ticket_date'] ?? null,
            'date_from' => isset($row['date_from']) ? substr((string) $row['date_from'], 0, 10) : null,
            'date_to' => isset($row['date_to']) ? substr((string) $row['date_to'], 0, 10) : null,
            'driver' => $row['driver'] ?? ($row['corporate'] ?? null),
            'customer_email' => $row['customer_email'] ?? null,
        ]);
    }

    public function closeDetails(): void
    {
        $this->showDetails = false;
        $this->detailsRow = null;
    }

    public function createFromResult(int $index): void
    {
        $row = $this->results[$index] ?? null;
        if ($row === null) {
            Notification::make()->warning()->title('Row not found')->send();

            return;
        }

        $this->startCreateFromRow($row);
    }

    public function useDetailsForCreate(): void
    {
        if ($this->detailsRow === null) {
            return;
        }

        $row = $this->detailsRow;
        $this->closeDetails();
        $this->startCreateFromRow($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function startCreateFromRow(array $row): void
    {
        if ($this->resultMode === 'contracts') {
            $this->selected = app(WheelsysClient::class)->rentalAsWheelsysDraft($row);
        } else {
            $this->selected = $row;
        }

        $this->logManualSearchAccess('create_in_wheels', [
            'mode' => $this->resultMode,
            'vehicle' => $row['vehicle'] ?? null,
            'wheels_id' => isset($row['id']) ? (string) $row['id'] : null,
            'agr_no' => $row['agr_no'] ?? null,
            'ticket_number' => $row['ticket_number'] ?? null,
            'driver' => $row['driver'] ?? ($row['corporate'] ?? null),
        ]);

        $this->prepareCreateInWheels();
    }

    /**
     * GDPR access audit: who searched / viewed Manual search data.
     *
     * @param  array<string, mixed>  $meta
     */
    private function logManualSearchAccess(string $event, array $meta): void
    {
        $meta = array_filter(
            $meta,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        ActivityLogger::log(
            'Manual search: '.$event,
            meta: $meta + ['source' => 'manual_search']
        );
    }

    /**
     * Flattened field list for the details popup (excludes raw blob).
     *
     * @return list<array{label: string, value: string}>
     */
    public function getDetailsFieldsProperty(): array
    {
        if ($this->detailsRow === null) {
            return [];
        }

        $labels = [
            'id' => 'ID',
            'vehicle' => 'Vehicle',
            'ticket_number' => 'Reference no',
            'ticket_date' => 'Ticket date',
            'ticket_datetime' => 'Ticket datetime',
            'fine_received_date' => 'Fine received',
            'ticket_type' => 'Ticket type',
            'agr_no' => 'RNT / Agreement',
            'date_from' => 'Contract from',
            'date_to' => 'Contract to',
            'status' => 'Status',
            'driver' => 'Driver',
            'birth_date' => 'Birth date',
            'customer_email' => 'Email',
            'driver_telephone' => 'Telephone',
            'driver_address' => 'Address',
            'driver_city' => 'City',
            'driver_country' => 'Country',
            'driver_license' => 'Licence no',
            'licence_issue_date' => 'Licence issued',
            'licence_issue_place' => 'Licence place',
            'driver_tax_id' => 'Driver tax ID',
            'driver_id_number' => 'Driver ID no',
            'driver_id' => 'Driver ID',
            'driver_code_id' => 'Driver code',
            'authority_email' => 'Authority email',
            'authority_name' => 'Authority',
            'agent' => 'Agent',
            'corporate' => 'Corporate',
            'station' => 'Station',
            'charge' => 'Charge',
            'admin_fee' => 'Admin fee',
        ];

        $fields = [];
        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $this->detailsRow)) {
                continue;
            }
            $value = $this->detailsRow[$key];
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            } elseif (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $fields[] = [
                'label' => $label,
                'value' => (string) $value,
            ];
        }

        // Any other scalar keys not in the label map.
        foreach ($this->detailsRow as $key => $value) {
            if ($key === 'raw' || isset($labels[$key])) {
                continue;
            }
            if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $fields[] = [
                'label' => (string) $key,
                'value' => is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
            ];
        }

        return $fields;
    }

    public function prepareCreateInWheels(): void
    {
        $draft = $this->selected;

        if ($draft === null) {
            Notification::make()
                ->warning()
                ->title('Nothing to create')
                ->body('Click Create in Wheels next to a found fine or contract.')
                ->send();

            return;
        }

        session(['wheelsys_create_draft' => $draft]);

        $this->redirect(route('wheelsys.create-traffic-violation'));
    }
}
