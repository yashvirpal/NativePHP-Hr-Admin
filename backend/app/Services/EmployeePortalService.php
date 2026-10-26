<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single place for everything an employee can do or see about themselves.
 *
 * Used by BOTH the mobile/REST API (Api\V1\*) and the employee web portal (Employee\*),
 * so the two clients can never drift apart. Controllers stay thin: validate, call here, render.
 * Every method is scoped to the Employee passed in; cross-employee access is refused here, not in the clients.
 */
class EmployeePortalService
{
    public function __construct(
        private AttendanceService $attendance,
        private LeaveService $leave,
        private OvertimeService $overtime,
    ) {}

    // ─── Dashboard ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function dashboard(Employee $employee): array
    {
        $employee->loadMissing('user', 'department', 'shift', 'manager.user');

        $today = $this->attendance->today($employee);
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        $month = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $monthStart)
            ->get();

        return [
            'employee' => $employee,
            'today' => $today,
            'is_punched_in' => $this->isPunchedIn($today),
            'balances' => $this->leaveBalances($employee),
            'upcoming_holidays' => Holiday::whereDate('date', '>=', Carbon::today())->orderBy('date')->limit(3)->get(),
            'month' => [
                'days_present' => $month->whereIn('status', ['present', 'late'])->count(),
                'days_late' => $month->where('status', 'late')->count(),
                'worked_minutes' => (int) $month->sum('worked_minutes'),
            ],
        ];
    }

    // ─── Attendance ─────────────────────────────────────────────────────────────

    public function today(Employee $employee): array
    {
        $record = $this->attendance->today($employee);

        return ['record' => $record, 'is_punched_in' => $this->isPunchedIn($record)];
    }

    public function punchIn(Employee $employee, array $meta = []): AttendanceRecord
    {
        return $this->attendance->punchIn($employee, $meta);
    }

    public function punchOut(Employee $employee, array $meta = []): AttendanceRecord
    {
        return $this->attendance->punchOut($employee, $meta);
    }

    public function attendanceHistory(Employee $employee, ?string $month = null): LengthAwarePaginator
    {
        return $this->attendance->history($employee, $month);
    }

    // ─── Leave ──────────────────────────────────────────────────────────────────

    public function leaveTypes(): Collection
    {
        return LeaveType::orderBy('name')->get();
    }

    /** @return Collection<int, array{leave_type: LeaveType, year: int, allocated: mixed, used: mixed, remaining: float}> */
    public function leaveBalances(Employee $employee, ?int $year = null): Collection
    {
        $year ??= Carbon::now()->year;

        return $this->leaveTypes()->map(function (LeaveType $type) use ($employee, $year) {
            $balance = $this->leave->balanceFor($employee, $type, $year);

            return [
                'leave_type' => $type,
                'year' => $year,
                'allocated' => $balance->allocated,
                'used' => $balance->used,
                'remaining' => $balance->remaining(),
            ];
        });
    }

    public function leaveRequests(Employee $employee): LengthAwarePaginator
    {
        return LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->paginate(15);
    }

    public function requestLeave(Employee $employee, int $leaveTypeId, string $start, string $end, ?string $reason = null): LeaveRequest
    {
        return $this->leave
            ->request($employee, LeaveType::findOrFail($leaveTypeId), $start, $end, $reason)
            ->load('leaveType');
    }

    public function cancelLeave(Employee $employee, LeaveRequest $request): LeaveRequest
    {
        $this->ensureOwns($employee, $request->employee_id);

        return $this->leave->cancel($request);
    }

    // ─── Overtime ───────────────────────────────────────────────────────────────

    public function overtimeRequests(Employee $employee): LengthAwarePaginator
    {
        return OvertimeRequest::where('employee_id', $employee->id)->orderByDesc('work_date')->paginate(15);
    }

    public function requestOvertime(Employee $employee, string $workDate, int $minutes, ?string $reason = null): OvertimeRequest
    {
        return $this->overtime->request($employee, $workDate, $minutes, $reason);
    }

    /**
     * The employee's current overtime rate and its history (newest first).
     *
     * @return array{rate: ?string, effective_from: ?string, history: list<array{hourly_rate: string, effective_from: string}>}
     */
    public function overtimeRate(Employee $employee): array
    {
        $history = $this->overtime->rateHistory($employee);
        $current = $history->first();

        return [
            'rate' => $current?->hourly_rate,
            'effective_from' => $current?->effective_from?->toIso8601String(),
            'history' => $history->map(fn ($r) => [
                'hourly_rate' => $r->hourly_rate,
                'effective_from' => $r->effective_from->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** Sets the hourly rate for overtime submitted from now on; existing requests keep their own rate. */
    public function setOvertimeRate(Employee $employee, string|int|float $hourlyRate): array
    {
        $this->overtime->setRate($employee, $hourlyRate);

        return $this->overtimeRate($employee);
    }

    // ─── Holidays ───────────────────────────────────────────────────────────────

    public function holidays(?int $year = null): Collection
    {
        return Holiday::whereYear('date', $year ?? Carbon::now()->year)->orderBy('date')->get();
    }

    // ─── Employer / company details ─────────────────────────────────────────────

    public function companies(Employee $employee): Collection
    {
        return $employee->companies()->orderByDesc('is_current')->orderByDesc('started_on')->get();
    }

    public function addCompany(Employee $employee, array $data): Company
    {
        return $employee->companies()->create($data);
    }

    public function updateCompany(Employee $employee, Company $company, array $data): Company
    {
        $this->ensureOwns($employee, $company->employee_id);
        $company->update($data);

        return $company;
    }

    public function deleteCompany(Employee $employee, Company $company): void
    {
        $this->ensureOwns($employee, $company->employee_id);
        $company->delete();
    }

    // ─── Internals ──────────────────────────────────────────────────────────────

    private function isPunchedIn(?AttendanceRecord $record): bool
    {
        return $record?->punches->sortBy([['punched_at', 'asc'], ['id', 'asc']])->last()?->type === 'in';
    }

    private function ensureOwns(Employee $employee, int $ownerEmployeeId): void
    {
        abort_unless($employee->id === $ownerEmployeeId, 403, 'This record belongs to another employee.');
    }
}
