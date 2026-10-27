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

    /** Blank cells before day 1 in a Sun-first grid (0-6), from the 1st's day of week. */
    public int $leadingBlanks = 0;

    /** Previous month's trailing day numbers, filling the grid before day 1 (display only). */
    public array $leadingOverflowDays = [];

    /** Next month's leading day numbers, filling the grid after the last day (display only). */
    public array $trailingOverflowDays = [];

    /**
     * @var list<array{date: string, day: int, dateLabel: string, detail: string, status: string,
     *     statusKey: string, regularMinutes: int, overtimeMinutes: int, overtimeAmount: ?string}>
     */
    public array $rows = [];

    /** The tapped day's row, or null when the detail modal is closed. */
    public ?array $selectedDay = null;

    /** @var array{regular: string, overtime: string, overtimeAmount: ?string} */
    public array $monthTotals = ['regular' => '-', 'overtime' => '-', 'overtimeAmount' => null, 'leaveDays' => '-'];

    public function selectDay(string $date): void
    {
        $this->selectedDay = collect($this->rows)->firstWhere('date', $date);
    }

    public function closeDay(): void
    {
        $this->selectedDay = null;
    }

    /** Pushes the combined regular+overtime entry screen for the tapped day. */
    public function logHours(): void
    {
        if ($this->selectedDay === null) {
            return;
        }

        $date = $this->selectedDay['date'];
        $this->selectedDay = null;
        $this->navigate('/attendance/manual', ['date' => $date]);
    }

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
        $this->leadingBlanks = $start->dayOfWeek; // Carbon: 0 = Sunday ... 6 = Saturday
        $this->selectedDay = null;

        $days = $this->call(fn ($api) => $api->attendanceCalendar($this->month));

        if ($days === null) {
            return;
        }

        $prevMonthEnd = $start->copy()->subDay();
        $this->leadingOverflowDays = [];
        for ($i = $this->leadingBlanks - 1; $i >= 0; $i--) {
            $this->leadingOverflowDays[] = $prevMonthEnd->copy()->subDays($i)->day;
        }

        // The backend already classifies every day of the month (present/late/absent/leave/
        // logged/unmarked), combining punch, manual-entry, overtime and leave data.
        $this->rows = collect($days)->map(function (array $r) {
            $worked = (int) ($r['worked_minutes'] ?? 0);
            $statusKey = (string) ($r['status'] ?? '');

            return [
                'date' => $r['work_date'],
                'day' => Carbon::parse($r['work_date'])->day,
                'dateLabel' => Format::date($r['work_date'], 'D, d M'),
                'detail' => match (true) {
                    ! empty($r['first_in_at']) => 'In '.Format::time($r['first_in_at']).' - Out '.Format::time($r['last_out_at'] ?? null).' - '.Format::minutes($worked),
                    $statusKey === 'leave' => 'On leave',
                    $statusKey === 'logged' => 'Logged manually (no punch)',
                    default => 'No punches recorded',
                },
                'status' => $statusKey === 'leave' ? (string) ($r['leave_type'] ?? 'Leave') : Format::status($statusKey),
                'statusKey' => $statusKey,
                'regularMinutes' => (int) ($r['regular_minutes'] ?? 0),
                'overtimeMinutes' => (int) ($r['overtime_minutes'] ?? 0),
                'overtimeAmount' => $r['overtime_amount'] ?? null,
            ];
        })->all();

        $trailingCount = (7 - (($this->leadingBlanks + count($this->rows)) % 7)) % 7;
        $nextMonthStart = $start->copy()->addMonth();
        $this->trailingOverflowDays = [];
        for ($i = 0; $i < $trailingCount; $i++) {
            $this->trailingOverflowDays[] = $nextMonthStart->copy()->addDays($i)->day;
        }

        $totals = $this->call(fn ($api) => $api->attendanceTotals($this->month));

        if ($totals !== null) {
            $this->monthTotals = $this->formatTotals($totals['month'] ?? []);
        }
    }

    /** @param array{regular_minutes?: int, overtime_minutes?: int, overtime_amount?: ?string, leave_days?: string} $t
     *  @return array{regular: string, overtime: string, overtimeAmount: ?string, leaveDays: string} */
    private function formatTotals(array $t): array
    {
        return [
            'regular' => Format::minutes($t['regular_minutes'] ?? 0),
            'overtime' => Format::minutes($t['overtime_minutes'] ?? 0),
            'overtimeAmount' => $t['overtime_amount'] ?? null,
            'leaveDays' => Format::days($t['leave_days'] ?? 0),
        ];
    }

    public function render(): View
    {
        return view('native.attendance');
    }
}
