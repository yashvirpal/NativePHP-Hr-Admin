<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\OvertimeRate;
use App\Models\OvertimeRequest;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    public function __construct(private ApprovalService $approvals) {}

    // ─── Rate (per hour) ────────────────────────────────────────────────────────

    /** The rate in force right now, or null if the employee never set one. */
    public function currentRate(Employee $employee): ?OvertimeRate
    {
        return $employee->overtimeRates()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Change the hourly rate going forward.
     *
     * Adds a history row only; every existing overtime request keeps the rate and amount it was
     * submitted with. Setting the rate to the value it already has is a no-op.
     */
    public function setRate(Employee $employee, string|int|float $hourlyRate): OvertimeRate
    {
        $rate = number_format((float) $hourlyRate, 2, '.', '');

        return DB::transaction(function () use ($employee, $rate) {
            $current = $this->currentRate($employee);

            if ($current !== null && $current->hourly_rate === $rate) {
                return $current;
            }

            return $employee->overtimeRates()->create([
                'hourly_rate' => $rate,
                'effective_from' => now(),
            ]);
        });
    }

    /** Newest first. */
    public function rateHistory(Employee $employee, int $limit = 10)
    {
        return $employee->overtimeRates()
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->limit($limit)->get();
    }

    // ─── Requests ───────────────────────────────────────────────────────────────

    public function request(Employee $employee, string $workDate, int $minutes, ?string $reason = null): OvertimeRequest
    {
        // Snapshot: whatever the rate is NOW is what this request is worth, forever.
        $rate = $this->currentRate($employee)?->hourly_rate;

        return OvertimeRequest::create([
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'minutes' => $minutes,
            'rate_per_hour' => $rate,
            'amount' => $rate === null ? null : self::amount($minutes, $rate),
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    public function decide(OvertimeRequest $request, Admin $approver, string $decision, ?string $remarks = null): OvertimeRequest
    {
        return DB::transaction(function () use ($request, $approver, $decision, $remarks) {
            $request = OvertimeRequest::with('employee')->lockForUpdate()->findOrFail($request->id);

            $this->approvals->decide($request, $approver, $decision, $remarks);

            return $request->fresh(['employee.user', 'approvals']);
        });
    }

    /** minutes at an hourly rate, to 2 decimals (e.g. 90 min at 150.00/h = 225.00). */
    public static function amount(int $minutes, string|int|float $hourlyRate): string
    {
        return number_format(round($minutes / 60 * (float) $hourlyRate, 2), 2, '.', '');
    }
}
