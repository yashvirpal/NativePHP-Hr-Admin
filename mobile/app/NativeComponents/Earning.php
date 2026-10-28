<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;

/** Regular hours, overtime hours/amount and leave days taken, over a selectable period. */
class Earning extends Screen
{
    /** 'this_month' | 'last_month' | '3' | '6' | '12' | 'custom' — drives which tab is highlighted. */
    public string $activePreset = 'this_month';

    /** 3 | 6 | 12 - only meaningful for the trailing-window presets. */
    public int $months = 1;

    /** True for both "Last month" and an applied custom range — both request an explicit [from, to]. */
    public bool $useCustomRange = false;

    /** Whether the custom-range inputs are visible (independent of whether they're applied). */
    public bool $showingCustomInputs = false;

    public string $customFrom = '';

    public string $customTo = '';

    public string $periodLabel = '';

    public string $regular = '-';

    public string $overtime = '-';

    public ?string $overtimeAmount = null;

    public string $leaveDays = '-';

    public function navTitle(): string
    {
        return __('Earning');
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

    public function selectThisMonth(): void
    {
        $this->activePreset = 'this_month';
        $this->useCustomRange = false;
        $this->showingCustomInputs = false;
        $this->months = 1;
        $this->load();
    }

    public function selectLastMonth(): void
    {
        $this->activePreset = 'last_month';
        $this->showingCustomInputs = false;
        $this->useCustomRange = true;
        $this->customFrom = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->customTo = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $this->load();
    }

    public function selectPeriod(int $months): void
    {
        $this->activePreset = (string) $months;
        $this->useCustomRange = false;
        $this->showingCustomInputs = false;
        $this->months = in_array($months, [3, 6, 12], true) ? $months : 3;
        $this->load();
    }

    public function showCustomRange(): void
    {
        $this->activePreset = 'custom';
        $this->showingCustomInputs = true;

        // iOS's compact date-picker never commits a selection until it has adopted a non-empty
        // value at least once (its internal "has a real selection" flag only flips on an inbound
        // value or its own placeholder tap) - so these can't start as empty strings.
        if ($this->customFrom === '') {
            $this->customFrom = now()->subDays(30)->toDateString();
        }

        if ($this->customTo === '') {
            $this->customTo = now()->toDateString();
        }
    }

    /**
     * Bound to the date-pickers' @change, not native:model — @change hands us the committed
     * ISO value directly as a parameter, so there's no dependency on sync timing between a
     * picker's own round-trip and a later button press.
     */
    public function setCustomFrom(string $value): void
    {
        $this->customFrom = $value;
    }

    public function setCustomTo(string $value): void
    {
        $this->customTo = $value;
    }

    public function applyCustomRange(): void
    {
        $from = trim($this->customFrom);
        $to = trim($this->customTo);

        if ($from === '' || $to === '') {
            $this->error = '';
            $this->fieldErrors = ['customFrom' => __('Enter both a start and end date.')];

            return;
        }

        if ($from > $to) {
            $this->error = '';
            $this->fieldErrors = ['customFrom' => __('The start date must be before the end date.')];

            return;
        }

        $this->useCustomRange = true;
        $this->load();
    }

    private function load(): void
    {
        $this->busy = true;
        $data = $this->useCustomRange
            ? $this->call(fn ($api) => $api->earnings(null, trim($this->customFrom), trim($this->customTo)))
            : $this->call(fn ($api) => $api->earnings($this->months));
        $this->busy = false;

        if ($data === null) {
            return;
        }

        $this->periodLabel = Format::date($data['from'] ?? null, 'd M Y').' - '.Format::date($data['to'] ?? null, 'd M Y');
        $this->regular = Format::minutes($data['regular_minutes'] ?? 0);
        $this->overtime = Format::minutes($data['overtime_minutes'] ?? 0);
        $this->overtimeAmount = $data['overtime_amount'] ?? null;
        $this->leaveDays = Format::days($data['leave_days'] ?? 0);
    }

    public function render(): View
    {
        return view('native.earning');
    }
}
