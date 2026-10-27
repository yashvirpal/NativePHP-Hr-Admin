<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;

/** Regular hours, overtime hours/amount and leave days taken, over a selectable period. */
class Earning extends Screen
{
    /** 1 | 3 | 6 | 12 - ignored while a custom range is active. */
    public int $months = 1;

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
        return 'Earning';
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

    public function selectPeriod(int $months): void
    {
        $this->useCustomRange = false;
        $this->showingCustomInputs = false;
        $this->months = in_array($months, [1, 3, 6, 12], true) ? $months : 1;
        $this->load();
    }

    public function showCustomRange(): void
    {
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
            $this->fieldErrors = ['customFrom' => 'Enter both a start and end date.'];

            return;
        }

        if ($from > $to) {
            $this->error = '';
            $this->fieldErrors = ['customFrom' => 'The start date must be before the end date.'];

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
