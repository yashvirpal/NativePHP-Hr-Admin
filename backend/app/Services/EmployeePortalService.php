<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ManualRegularEntry;
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

    /** Attendance records for the month, each annotated with that day's overtime_minutes/overtime_amount. */
    public function attendanceHistory(Employee $employee, ?string $month = null): LengthAwarePaginator
    {
        $page = $this->attendance->history($employee, $month);
        $records = $page->getCollection();

        $dates = $records->map(fn (AttendanceRecord $r) => $r->work_date->toDateString());

        $overtimeByDate = OvertimeRequest::where('employee_id', $employee->id)
            ->whereIn('work_date', $dates)
            ->selectRaw('work_date, sum(minutes) as minutes, sum(amount) as amount')
            ->groupBy('work_date')
            ->get()
            ->keyBy(fn (OvertimeRequest $r) => $r->work_date->toDateString());

        $records->each(function (AttendanceRecord $record) use ($overtimeByDate) {
            $overtime = $overtimeByDate->get($record->work_date->toDateString());
            $record->overtime_minutes = (int) ($overtime->minutes ?? 0);
            $record->overtime_amount = $overtime->amount ?? null;
        });

        return $page;
    }

    /** Independent of punches — see AttendanceService::logManualRegular(). */
    public function logManualRegularHours(Employee $employee, string $workDate, int $minutes, ?string $reason = null): ManualRegularEntry
    {
        return $this->attendance->logManualRegular($employee, $workDate, $minutes, $reason);
    }

    /**
     * Every day of the given month for the calendar view, each classified into exactly one
     * status: 'leave' (an approved leave request covers it — carries the leave type name),
     * the punch status ('present'/'late'/'half_day'/'absent') when a punch exists, 'logged'
     * when regular/overtime hours were declared manually with no punch, 'absent' when the day
     * is in the past with nothing at all recorded, or '' for today/future (not yet concluded).
     *
     * @return list<array{work_date:string, status:string, leave_type:?string, first_in_at:?string,
     *     last_out_at:?string, worked_minutes:int, regular_minutes:int, overtime_minutes:int, overtime_amount:?string}>
     */
    public function attendanceCalendar(Employee $employee, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $recordsByDate = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRecord $r) => $r->work_date->toDateString());

        $manualByDate = ManualRegularEntry::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ManualRegularEntry $r) => $r->work_date->toDateString());

        $overtimeByDate = OvertimeRequest::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (OvertimeRequest $r) => $r->work_date->toDateString());

        $leaveRequests = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();

        $days = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $record = $recordsByDate->get($date);
            $overtime = $overtimeByDate->get($date);
            $leave = $leaveRequests->first(fn (LeaveRequest $l) => $l->start_date->lte($d) && $l->end_date->gte($d));

            $worked = (int) ($record?->worked_minutes ?? 0);
            $overtimeMinutes = (int) ($overtime?->minutes ?? 0);
            $manualMinutes = (int) ($manualByDate->get($date)?->minutes ?? 0);
            $regularMinutes = max(0, $worked - $overtimeMinutes) + $manualMinutes;

            $status = match (true) {
                $leave !== null => 'leave',
                $record !== null => $record->status,
                $regularMinutes > 0 || $overtimeMinutes > 0 => 'logged',
                $d->lt($today) => 'absent',
                default => '',
            };

            $days[] = [
                'work_date' => $date,
                'status' => $status,
                'leave_type' => $leave?->leaveType?->name,
                'first_in_at' => $record?->first_in_at?->toIso8601String(),
                'last_out_at' => $record?->last_out_at?->toIso8601String(),
                'worked_minutes' => $worked,
                'regular_minutes' => $regularMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'overtime_amount' => $overtime?->amount,
            ];
        }

        return $days;
    }

    /**
     * The employee's manual regular minutes, overtime minutes/amount, and leave status for one
     * specific day — what the "log hours" screen prefills when the employee taps that day again.
     *
     * @return array{regular_minutes:int, overtime_minutes:int, overtime_amount:?string, on_leave:bool, leave_type:?string}
     */
    public function dayEntry(Employee $employee, string $workDate): array
    {
        $regular = $this->attendance->manualRegularFor($employee, $workDate);
        $overtime = $this->overtime->requestFor($employee, $workDate);

        $leave = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $workDate)
            ->whereDate('end_date', '>=', $workDate)
            ->first();

        return [
            'regular_minutes' => $regular?->minutes ?? 0,
            'overtime_minutes' => $overtime?->minutes ?? 0,
            'overtime_amount' => $overtime?->amount,
            'on_leave' => $leave !== null,
            'leave_type' => $leave?->leaveType?->name,
        ];
    }

    /**
     * Regular/overtime minute totals (plus leave days taken) for the given month and for its
     * whole year, combining punch-derived regular minutes (worked_minutes - that day's
     * overtime) with manually logged regular entries. Clamped per day before summing, never
     * negative. Also includes the employee's all-time overtime total, independent of month.
     *
     * @return array{month: array{regular_minutes:int, overtime_minutes:int, overtime_amount:?string, leave_days:string},
     *               year: array{regular_minutes:int, overtime_minutes:int, overtime_amount:?string, leave_days:string},
     *               lifetime: array{overtime_minutes:int, overtime_amount:?string}}
     */
    public function attendanceTotals(Employee $employee, ?string $month = null): array
    {
        $monthStart = $month !== null
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $lifetimeOvertime = OvertimeRequest::where('employee_id', $employee->id)
            ->selectRaw('sum(minutes) as minutes, sum(amount) as amount')
            ->first();

        return [
            'month' => $this->totalsFor($employee, $monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()),
            'year' => $this->totalsFor($employee, $monthStart->copy()->startOfYear()->toDateString(), $monthStart->copy()->endOfYear()->toDateString()),
            'lifetime' => [
                'overtime_minutes' => (int) ($lifetimeOvertime->minutes ?? 0),
                'overtime_amount' => $lifetimeOvertime->amount !== null ? number_format((float) $lifetimeOvertime->amount, 2, '.', '') : null,
            ],
        ];
    }

    /**
     * Regular hours, overtime hours/amount and leave days taken, either over a trailing window
     * of $months (including the current month) or over an explicit [$from, $to] custom range
     * — for the mobile "Earning" screen. A custom range takes precedence when given.
     *
     * @return array{months:?int, from:string, to:string, regular_minutes:int, overtime_minutes:int,
     *     overtime_amount:?string, leave_days:string}
     */
    public function earningsSummary(Employee $employee, ?int $months = null, ?string $from = null, ?string $to = null): array
    {
        if ($from !== null && $to !== null) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();
            $months = null;
        } else {
            $toDate = Carbon::now()->endOfMonth();
            $fromDate = Carbon::now()->startOfMonth()->subMonths(($months ?? 1) - 1);
        }

        $totals = $this->totalsFor($employee, $fromDate->toDateString(), $toDate->toDateString());

        return [
            'months' => $months,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'regular_minutes' => $totals['regular_minutes'],
            'overtime_minutes' => $totals['overtime_minutes'],
            'overtime_amount' => $totals['overtime_amount'],
            'leave_days' => $totals['leave_days'],
        ];
    }

    /** @return array{regular_minutes:int, overtime_minutes:int, overtime_amount:?string, leave_days:string} */
    private function totalsFor(Employee $employee, string $from, string $to): array
    {
        $workedByDate = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->get(['work_date', 'worked_minutes'])
            ->mapWithKeys(fn (AttendanceRecord $r) => [$r->work_date->toDateString() => (int) $r->worked_minutes]);

        $overtimeByDate = OvertimeRequest::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->selectRaw('work_date, sum(minutes) as minutes, sum(amount) as amount')
            ->groupBy('work_date')
            ->get()
            ->keyBy(fn (OvertimeRequest $r) => $r->work_date->toDateString());

        $punchRegularMinutes = 0;
        foreach ($workedByDate as $date => $worked) {
            $punchRegularMinutes += max(0, $worked - (int) ($overtimeByDate->get($date)->minutes ?? 0));
        }

        $manualRegularMinutes = (int) ManualRegularEntry::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->sum('minutes');

        $overtimeMinutes = (int) $overtimeByDate->sum('minutes');
        $overtimeAmountSum = $overtimeByDate->sum('amount');

        // Any approved leave overlapping the range counts its full days, even if the leave
        // itself spans past either edge — same simplification attendanceCalendar() accepts.
        $leaveDays = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->sum('days');

        return [
            'regular_minutes' => $punchRegularMinutes + $manualRegularMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_amount' => $overtimeByDate->isNotEmpty() ? number_format((float) $overtimeAmountSum, 2, '.', '') : null,
            'leave_days' => number_format((float) $leaveDays, 1, '.', ''),
        ];
    }

    // ─── Leave ──────────────────────────────────────────────────────────────────

    public function leaveTypes(): Collection
    {
        return LeaveType::orderBy('name')->get();
    }

    /**
     * Only Annual Leave ('AL') — the one leave type employees see on their dashboard and Leave
     * screen; other types still exist for admin reporting but aren't surfaced here.
     *
     * @return Collection<int, array{leave_type: LeaveType, year: int, allocated: mixed, used: mixed, remaining: float}>
     */
    public function leaveBalances(Employee $employee, ?int $year = null): Collection
    {
        $year ??= Carbon::now()->year;

        return $this->leaveTypes()
            ->filter(fn (LeaveType $type) => $type->code === 'AL')
            ->map(function (LeaveType $type) use ($employee, $year) {
                $balance = $this->leave->balanceFor($employee, $type, $year);

                return [
                    'leave_type' => $type,
                    'year' => $year,
                    'allocated' => $balance->allocated,
                    'used' => $balance->used,
                    'remaining' => $balance->remaining(),
                ];
            })->values();
    }

    public function leaveRequests(Employee $employee): LengthAwarePaginator
    {
        return LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->paginate(15);
    }

    /** No $leaveTypeId means Annual Leave — what the calendar's "mark as leave" flow always means. */
    public function requestLeave(Employee $employee, ?int $leaveTypeId, string $start, string $end, ?string $reason = null): LeaveRequest
    {
        $type = $leaveTypeId !== null
            ? LeaveType::findOrFail($leaveTypeId)
            : LeaveType::where('code', 'AL')->firstOrFail();

        return $this->leave
            ->request($employee, $type, $start, $end, $reason)
            ->load('leaveType');
    }

    public function cancelLeave(Employee $employee, LeaveRequest $request): LeaveRequest
    {
        $this->ensureOwns($employee, $request->employee_id);

        return $this->leave->cancel($request);
    }

    // ─── Overtime ───────────────────────────────────────────────────────────────

    /** Unfiltered (web): paginated 15/page, newest first. Scoped to $month (mobile): one page, up to a full month. */
    public function overtimeRequests(Employee $employee, ?string $month = null): LengthAwarePaginator
    {
        $query = OvertimeRequest::where('employee_id', $employee->id)->orderByDesc('work_date');

        if ($month === null) {
            return $query->paginate(15);
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $query->whereBetween('work_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);

        return $query->paginate(31);
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
