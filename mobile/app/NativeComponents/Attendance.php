<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class Attendance extends Screen
{
    /** Y-m */
    public string $month = '';

    public string $monthLabel = '';

    public bool $canGoNext = false;

    /** @var list<array{date: string, detail: string, status: string, statusKey: string}> */
    public array $rows = [];

    public function navTitle(): string
    {
        return 'Attendance';
    }

    public function activeTab(): ?string
    {
        return '/attendance';
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

    private function load(): void
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $this->monthLabel = $start->format('F Y');
        $this->canGoNext = $start->lt(now()->startOfMonth());

        $page = $this->call(fn ($api) => $api->attendance($this->month));

        if ($page === null) {
            return;
        }

        $this->rows = collect($page['data'] ?? [])->map(fn ($r) => [
            'date' => Format::date($r['work_date'] ?? null, 'D, d M'),
            'detail' => ! empty($r['first_in_at'])
                ? 'In '.Format::time($r['first_in_at']).' - Out '.Format::time($r['last_out_at'] ?? null).' - '.Format::minutes($r['worked_minutes'] ?? 0)
                : 'No punches recorded',
            'status' => Format::status($r['status'] ?? null),
            'statusKey' => (string) ($r['status'] ?? ''),
        ])->all();
    }

    public function render(): View
    {
        return view('native.attendance');
    }
}
