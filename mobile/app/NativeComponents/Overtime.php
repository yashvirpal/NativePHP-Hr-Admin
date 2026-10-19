<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;

class Overtime extends Screen
{
    /** Current hourly rate, formatted ("150.00"), or '' when none is set. */
    public string $rate = '';

    /** @var list<array{date: string, duration: string, priced: string, reason: string, status: string, statusKey: string}> */
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

    public function newRequest(): void
    {
        $this->navigate('/overtime/new');
    }

    public function changeRate(): void
    {
        $this->navigate('/overtime/rate');
    }

    private function load(): void
    {
        $rate = $this->call(fn ($api) => $api->overtimeRate());
        if ($rate === null) {
            return;
        }
        $this->rate = ($rate['rate'] ?? null) !== null ? Format::money($rate['rate']) : '';

        $page = $this->call(fn ($api) => $api->overtimeRequests());
        if ($page === null) {
            return;
        }

        $this->requests = collect($page['data'] ?? [])->map(function ($r) {
            // Each request shows the rate IT was submitted with, not the employee's current rate.
            $priced = ($r['amount'] ?? null) !== null
                ? Format::money($r['amount']).' ('.Format::money($r['rate_per_hour']).' per hour)'
                : 'No rate set';

            return [
                'date' => Format::date($r['work_date'] ?? null, 'D, d M Y'),
                'duration' => Format::minutes($r['minutes'] ?? 0),
                'priced' => $priced,
                'reason' => (string) ($r['reason'] ?? ''),
                'status' => Format::status($r['status'] ?? null),
                'statusKey' => (string) ($r['status'] ?? ''),
            ];
        })->all();
    }

    public function render(): View
    {
        return view('native.overtime');
    }
}
