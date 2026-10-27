<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class Overtime extends Screen
{
    /** Current hourly rate, formatted ("150.00"), or '' when none is set. */
    public string $rate = '';

    /** Y-m - which month's requests are showing, browsed with Prev/Next rather than one long list. */
    public string $month = '';

    public string $monthLabel = '';

    public bool $canGoNext = false;

    public string $totalDuration = '-';

    public ?string $totalAmount = null;

    /** @var list<array{date: string, duration: string, amount: ?string, ratePerHour: ?string, reason: string}> */
    public array $requests = [];

    public function navTitle(): string
    {
        return 'Overtime';
    }

    public function activeTab(): ?string
    {
        return '/overtime';
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function refresh(): void
    {
        $this->load();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
        $this->load();
    }

    public function nextMonth(): void
    {
        if (! $this->canGoNext) {
            return;
        }

        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
        $this->load();
    }

    public function changeRate(): void
    {
        $this->navigate('/overtime/rate');
    }

    private function load(): void
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $this->monthLabel = $start->format('F Y');
        $this->canGoNext = $start->lt(now()->startOfMonth());

        $rate = $this->call(fn ($api) => $api->overtimeRate());
        if ($rate === null) {
            return;
        }
        $this->rate = ($rate['rate'] ?? null) !== null ? Format::money($rate['rate']) : '';

        $page = $this->call(fn ($api) => $api->overtimeRequests($this->month));
        if ($page === null) {
            return;
        }

        $rows = collect($page['data'] ?? [])->map(fn ($r) => [
            'date' => Format::date($r['work_date'] ?? null, 'D, d M Y'),
            'minutes' => (int) ($r['minutes'] ?? 0),
            'duration' => Format::minutes($r['minutes'] ?? 0),
            'amountValue' => ($r['amount'] ?? null) !== null ? (float) $r['amount'] : null,
            // Each request shows the rate IT was submitted with, not the employee's current rate.
            'amount' => ($r['amount'] ?? null) !== null ? Format::money($r['amount']) : null,
            'ratePerHour' => ($r['rate_per_hour'] ?? null) !== null ? Format::money($r['rate_per_hour']).'/hr' : null,
            'reason' => (string) ($r['reason'] ?? ''),
        ]);

        $this->totalDuration = Format::minutes($rows->sum('minutes'));
        $this->totalAmount = $rows->contains(fn ($r) => $r['amountValue'] !== null)
            ? Format::money($rows->sum('amountValue'))
            : null;

        $this->requests = $rows->map(fn ($r) => [
            'date' => $r['date'],
            'duration' => $r['duration'],
            'amount' => $r['amount'],
            'ratePerHour' => $r['ratePerHour'],
            'reason' => $r['reason'],
        ])->values()->all();
    }

    public function render(): View
    {
        return view('native.overtime');
    }
}
