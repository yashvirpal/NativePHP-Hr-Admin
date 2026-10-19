<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

class OvertimeCreate extends Screen
{
    public string $workDate = '';

    /** Kept as text because that is what a keyboard input produces. */
    public string $minutes = '60';

    public string $reason = '';

    public function navTitle(): string
    {
        return 'New overtime request';
    }

    public function mount(): void
    {
        $this->workDate = now()->toDateString();
    }

    public function submit(): void
    {
        $minutes = trim($this->minutes);

        if (! ctype_digit($minutes) || (int) $minutes < 1) {
            $this->error = '';
            $this->fieldErrors = ['minutes' => 'Enter the number of minutes worked, e.g. 90.'];

            return;
        }

        $this->busy = true;
        $created = $this->call(fn ($api) => $api->requestOvertime(
            trim($this->workDate), (int) $minutes, trim($this->reason) ?: null,
        ));
        $this->busy = false;

        if ($created !== null) {
            Dialog::toast('Overtime request submitted', 'short');
            $this->back();
        }
    }

    public function render(): View
    {
        return view('native.overtime-create');
    }
}
