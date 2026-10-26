<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

/**
 * Lets an employee declare regular and/or overtime hours for a specific day directly,
 * independent of the punch clock (not reconciled or merged with attendance_records). Tapping
 * the same day again prefills whatever is already logged; only the field(s) that actually
 * change get resubmitted.
 */
class ManualHoursCreate extends Screen
{
    public string $workDate = '';

    /** Kept as text because that is what a keyboard input produces. */
    public string $regularHours = '';

    public string $overtimeHours = '';

    public string $reason = '';

    /** Minutes already on the server when this screen opened — used to skip unchanged fields on submit. */
    public int $initialRegularMinutes = 0;

    public int $initialOvertimeMinutes = 0;

    public function navTitle(): string
    {
        return 'Log hours';
    }

    public function mount(): void
    {
        $this->workDate = (string) ($this->data('date') ?? now()->toDateString());

        $day = $this->call(fn ($api) => $api->dayEntry($this->workDate));

        if ($day === null) {
            return;
        }

        $this->initialRegularMinutes = (int) ($day['regular_minutes'] ?? 0);
        $this->initialOvertimeMinutes = (int) ($day['overtime_minutes'] ?? 0);
        $this->regularHours = $this->initialRegularMinutes > 0 ? self::hoursLabel($this->initialRegularMinutes) : '';
        $this->overtimeHours = $this->initialOvertimeMinutes > 0 ? self::hoursLabel($this->initialOvertimeMinutes) : '';
    }

    public function submit(): void
    {
        $regular = trim($this->regularHours);
        $overtime = trim($this->overtimeHours);

        if ($regular === '' && $overtime === '') {
            $this->error = '';
            $this->fieldErrors = ['regularHours' => 'Enter regular and/or overtime hours for this day.'];

            return;
        }

        foreach (['regularHours' => $regular, 'overtimeHours' => $overtime] as $field => $value) {
            if ($value === '') {
                continue;
            }

            if (! is_numeric($value) || (float) $value <= 0) {
                $this->error = '';
                $this->fieldErrors = [$field => 'Enter a number of hours greater than 0.'];

                return;
            }

            if ((float) $value > 24) {
                $this->error = '';
                $this->fieldErrors = [$field => "Hours can't be more than 24 for a single day."];

                return;
            }
        }

        $reason = trim($this->reason) ?: null;
        $messages = [];
        $this->busy = true;

        // Blank means "no change" (not "clear it"); unchanged values are left alone entirely —
        // nothing is resubmitted, so the server's existing amount/rate snapshot stays untouched.
        if ($regular !== '' && self::toMinutes($regular) !== $this->initialRegularMinutes) {
            $result = $this->call(fn ($api) => $api->logManualRegularHours($this->workDate, self::toMinutes($regular), $reason));

            if ($result === null) {
                $this->remapMinutesError('regularHours');
                $this->busy = false;

                return;
            }

            $messages[] = 'Regular hours saved';
        }

        if ($overtime !== '' && self::toMinutes($overtime) !== $this->initialOvertimeMinutes) {
            $result = $this->call(fn ($api) => $api->requestOvertime($this->workDate, self::toMinutes($overtime), $reason));

            if ($result === null) {
                $this->remapMinutesError('overtimeHours');
                $this->busy = false;

                return;
            }

            $amount = $result['amount'] ?? null;
            $messages[] = $amount !== null ? "Overtime saved - {$amount}" : 'Overtime saved';
        }

        $this->busy = false;

        if ($messages !== []) {
            Dialog::toast(implode(' | ', $messages), 'short');
        }

        $this->back();
    }

    /** Backend validation errors land under 'minutes'; the visible field here has its own name. */
    private function remapMinutesError(string $field): void
    {
        if (isset($this->fieldErrors['minutes'])) {
            $this->fieldErrors[$field] = $this->fieldErrors['minutes'];
        }
    }

    private static function toMinutes(string $hours): int
    {
        return (int) round((float) $hours * 60);
    }

    /** 90 -> "1.5", 60 -> "1", 0 -> "0". */
    private static function hoursLabel(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 2, '.', ''), '0'), '.');
    }

    public function render(): View
    {
        return view('native.manual-hours-create');
    }
}
