<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Admin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(private ApprovalService $approvals) {}

    public function balanceFor(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['allocated' => $type->days_per_year, 'used' => 0],
        );
    }

    public function request(Employee $employee, LeaveType $type, string $start, string $end, ?string $reason = null): LeaveRequest
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($startDate->year !== $endDate->year) {
            throw ValidationException::withMessages(['end_date' => 'Leave cannot span two calendar years; submit separate requests.']);
        }

        $days = $this->workingDays($startDate, $endDate);
        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'The selected range contains no working days.']);
        }

        return DB::transaction(function () use ($employee, $type, $startDate, $endDate, $days, $reason) {
            $overlap = LeaveRequest::where('employee_id', $employee->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'You already have a leave request overlapping these dates.']);
            }

            if ($type->days_per_year > 0) {
                $balance = $this->balanceFor($employee, $type, $startDate->year);
                $pending = LeaveRequest::where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('status', 'pending')
                    ->whereYear('start_date', $startDate->year)
                    ->sum('days');

                if ($balance->remaining() - (float) $pending < $days) {
                    throw ValidationException::withMessages(['leave_type_id' => 'Insufficient leave balance.']);
                }
            }

            return LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'reason' => $reason,
                'status' => 'pending',
            ]);
        });
    }

    public function decide(LeaveRequest $request, Admin $approver, string $decision, ?string $remarks = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $approver, $decision, $remarks) {
            $request = LeaveRequest::with('employee')->lockForUpdate()->findOrFail($request->id);

            $this->approvals->decide($request, $approver, $decision, $remarks);

            if ($decision === 'approved' && $request->leaveType->days_per_year > 0) {
                $balance = $this->balanceFor($request->employee, $request->leaveType, $request->start_date->year);
                $balance->increment('used', (float) $request->days);
            }

            return $request->fresh(['employee.user', 'leaveType', 'approvals']);
        });
    }

    public function cancel(LeaveRequest $request): LeaveRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be cancelled.']);
        }

        $request->update(['status' => 'cancelled']);

        return $request;
    }

    /** Weekdays (Mon–Fri) in the inclusive range, minus non-optional holidays. */
    public function workingDays(Carbon $start, Carbon $end): int
    {
        $holidays = Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('is_optional', false)
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $count = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekday() && ! in_array($d->toDateString(), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }
}
