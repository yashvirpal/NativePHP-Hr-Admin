<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

/**
 * The employee's hourly overtime rate. A change applies to overtime submitted from now on;
 * the backend keeps every existing request at the rate it was submitted with.
 */
class OvertimeRate extends Screen
{
    /** What the keyboard input holds, e.g. "175" or "175.50". */
    public string $hourlyRate = '';

    public string $current = '';

    /** @var list<array{rate: string, since: string}> newest first */
    public array $history = [];

    public function navTitle(): string
    {
        return __('Overtime rate');
    }

    public function mount(): void
    {
        $this->load();
        $this->hourlyRate = $this->current === '' ? '' : str_replace(',', '', $this->current);
    }

    public function save(): void
    {
        $value = trim($this->hourlyRate);

        if (! preg_match('/^\d{1,5}(\.\d{1,2})?$/', $value)) {
            $this->error = '';
            $this->fieldErrors = ['hourly_rate' => __('Enter an amount like 150 or 175.50.')];

            return;
        }

        $this->busy = true;
        $saved = $this->call(fn ($api) => $api->setOvertimeRate($value));
        $this->busy = false;

        if ($saved !== null) {
            $this->apply($saved);
            Dialog::toast(__('Hourly rate updated'), 'short');
            $this->back();
        }
    }

    private function load(): void
    {
        $data = $this->call(fn ($api) => $api->overtimeRate());

        if ($data !== null) {
            $this->apply($data);
        }
    }

    private function apply(array $data): void
    {
        $this->current = ($data['rate'] ?? null) !== null ? Format::money($data['rate']) : '';

        $this->history = collect($data['history'] ?? [])->map(fn ($h) => [
            'rate' => Format::money($h['hourly_rate']),
            'since' => Format::date($h['effective_from'], 'd M Y'),
        ])->all();
    }

    public function render(): View
    {
        return view('native.overtime-rate');
    }
}
