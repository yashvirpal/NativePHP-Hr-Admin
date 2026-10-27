<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

/**
 * Lets an employee declare regular and/or overtime hours for a specific day directly,
 * independent of the punch clock (not reconciled or merged with attendance_records). Tapping
 * the same day again prefills whatever is already logged; only the field(s) that actually
 * change get resubmitted.
 *
 * Also lets the employee mark this day (or a range starting here) as leave — only offered for
 * today or a future day, since leave can't be backdated, and not at all once the day is already
 * on leave (shown read-only instead, to avoid the server's overlap rejection).
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

    public bool $onLeave = false;

    public ?string $leaveTypeName = null;

    /** Whether today/this day can still be requested as leave (false for a day already in the past). */
    public bool $canMarkLeave = false;

    public bool $markLeave = false;

    /** Defaults to $workDate; editable to mark a range starting here as leave. */
    public string $leaveEndDate = '';

    public function navTitle(): string
    {
        return 'Log hours';
    }

    public function mount(): void
    {
        $this->workDate = (string) ($this->data('date') ?? now()->toDateString());
        $this->canMarkLeave = $this->workDate >= now()->toDateString();
        $this->leaveEndDate = $this->workDate;

        $day = $this->call(fn ($api) => $api->dayEntry($this->workDate));

        if ($day === null) {
            return;
        }

        $this->initialRegularMinutes = (int) ($day['regular_minutes'] ?? 0);
        $this->initialOvertimeMinutes = (int) ($day['overtime_minutes'] ?? 0);
        $this->regularHours = $this->initialRegularMinutes > 0 ? self::hoursLabel($this->initialRegularMinutes) : '';
        $this->overtimeHours = $this->initialOvertimeMinutes > 0 ? self::hoursLabel($this->initialOvertimeMinutes) : '';
        $this->onLeave = (bool) ($day['on_leave'] ?? false);
        $this->leaveTypeName = $day['leave_type'] ?? null;
    }

    public function toggleMarkLeave(): void
    {
        $this->markLeave = ! $this->markLeave;
    }

    /** Bound to the leave end-date picker's @change — see setCustomFrom()/setCustomTo() in Earning for why. */
    public function setLeaveEndDate(string $value): void
    {
        $this->leaveEndDate = $value;
    }

    public function submit(): void
    {
        $regular = trim($this->regularHours);
        $overtime = trim($this->overtimeHours);
        $leaveEnd = trim($this->leaveEndDate);

        if ($regular === '' && $overtime === '' && ! $this->markLeave) {
            $this->error = '';
            $this->fieldErrors = ['regularHours' => 'Enter regular and/or overtime hours, or mark this day as leave.'];

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

        if ($this->markLeave && $leaveEnd < $this->workDate) {
            $this->error = '';
            $this->fieldErrors = ['leaveEndDate' => 'The end date must be on or after the start date.'];

            return;
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

        if ($this->markLeave) {
            $result = $this->call(fn ($api) => $api->requestLeave(null, $this->workDate, $leaveEnd, $reason));

            if ($result === null) {
                $this->busy = false;

                return;
            }

            $messages[] = 'Marked as leave';
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
