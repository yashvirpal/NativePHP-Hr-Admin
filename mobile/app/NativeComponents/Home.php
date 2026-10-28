<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;

class Home extends Screen
{
    public string $name = '';

    public string $subtitle = '';

    public bool $loaded = false;

    public bool $punchedIn = false;

    public bool $finishedToday = false;

    /** "Punched in since 09:02" etc. */
    public string $todayLine = '';

    public string $todayStatus = '';

    /** @var array{present: int, leaveDays: string, regular: string, overtime: string, overtimeAmount: ?string} */
    public array $month = ['present' => 0, 'leaveDays' => '-', 'regular' => '-', 'overtime' => '-', 'overtimeAmount' => null];

    /** @var array{regular: string, overtime: string, overtimeAmount: ?string} */
    public array $year = ['regular' => '-', 'overtime' => '-', 'overtimeAmount' => null];

    /** @var array{amount: ?string, hours: string} */
    public array $lifetimeOvertime = ['amount' => null, 'hours' => '-'];

    /** @var list<array{name: string, date: string, optional: bool}> */
    public array $holidays = [];

    public function navTitle(): string
    {
        return __('Home');
    }

    public function activeTab(): ?string
    {
        return '/home';
    }

    public function mount(): void
    {
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    /** Pull to refresh. */
    public function refresh(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $data = $this->call(fn ($api) => $api->dashboard());

        if ($data === null) {
            return;
        }

        $employee = $data['employee'] ?? [];
        $today = $data['today'] ?? null;

        $this->loaded = true;
        $this->name = (string) ($employee['user']['name'] ?? '');
        $this->subtitle = collect([$employee['designation'] ?? null, $employee['department']['name'] ?? null])->filter()->implode(' - ');

        $this->punchedIn = (bool) ($data['is_punched_in'] ?? false);
        $this->finishedToday = ! $this->punchedIn && ($today['last_out_at'] ?? null) !== null;
        $this->todayStatus = Format::status($today['status'] ?? null);
        $this->todayLine = match (true) {
            $this->punchedIn => __('Punched in since :time', ['time' => Format::time($today['first_in_at'] ?? null)]),
            $this->finishedToday => __('Last out at :time - :duration worked', [
                'time' => Format::time($today['last_out_at']),
                'duration' => Format::minutes($today['worked_minutes'] ?? 0),
            ]),
            default => __('Not punched in'),
        };

        $month = $data['month'] ?? [];
        $totals = $this->call(fn ($api) => $api->attendanceTotals());
        $monthTotals = $totals['month'] ?? [];
        $yearTotals = $totals['year'] ?? [];
        $lifetimeTotals = $totals['lifetime'] ?? [];

        $this->month = [
            'present' => (int) ($month['days_present'] ?? 0),
            'leaveDays' => Format::days($monthTotals['leave_days'] ?? 0),
            'regular' => Format::minutes($monthTotals['regular_minutes'] ?? 0),
            'overtime' => Format::minutes($monthTotals['overtime_minutes'] ?? 0),
            'overtimeAmount' => $monthTotals['overtime_amount'] ?? null,
        ];

        $this->year = [
            'regular' => Format::minutes($yearTotals['regular_minutes'] ?? 0),
            'overtime' => Format::minutes($yearTotals['overtime_minutes'] ?? 0),
            'overtimeAmount' => $yearTotals['overtime_amount'] ?? null,
        ];

        $this->lifetimeOvertime = [
            'amount' => $lifetimeTotals['overtime_amount'] ?? null,
            'hours' => Format::minutes($lifetimeTotals['overtime_minutes'] ?? 0),
        ];

        $this->holidays = collect($data['upcoming_holidays'] ?? [])->map(fn ($h) => [
            'name' => (string) $h['name'],
            'date' => Format::date($h['date'] ?? null, 'D, d M'),
            'optional' => (bool) ($h['is_optional'] ?? false),
        ])->all();
    }

    public function render(): View
    {
        return view('native.home');
    }
}
