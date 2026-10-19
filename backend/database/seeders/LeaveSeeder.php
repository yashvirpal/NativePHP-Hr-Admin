<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Leave balances (every employee × every leave type) and ~2 leave requests per employee.
 * Approvals for the decided ones are created by ApprovalSeeder.
 */
class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $year = Carbon::now()->year;
        $employees = Employee::orderBy('id')->get();
        $types = LeaveType::where('days_per_year', '>', 0)->orderBy('id')->get();

        foreach ($employees as $employee) {
            foreach ($types as $type) {
                LeaveBalance::firstOrCreate(
                    ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                    ['allocated' => $type->days_per_year, 'used' => 0],
                );
            }
        }

        $service = app(LeaveService::class);
        $usable = $types->whereIn('code', ['AL', 'SL', 'CL'])->values();
        $weekStart = Carbon::now()->startOfWeek();

        foreach ($employees as $i => $employee) {
            $pastStatus = ['approved', 'approved', 'rejected', 'cancelled'][$i % 4];
            $futureStatus = ['pending', 'pending', 'approved'][$i % 3];

            // Past request: 2–4 weeks ago, Mon–Thu start, 1–2 days. Future: 1–4 weeks ahead.
            $past = $weekStart->copy()->subWeeks(2 + $i % 3)->addDays($i % 4);
            $future = $weekStart->copy()->addWeeks(1 + $i % 4)->addDays(($i + 1) % 4);

            foreach ([[$past, $pastStatus, 'Personal errand'], [$future, $futureStatus, 'Planned time off']] as $n => [$start, $status, $reason]) {
                $end = $start->copy()->addDays($i % 2);
                $type = $usable[($i + $n) % $usable->count()];

                LeaveRequest::firstOrCreate(
                    ['employee_id' => $employee->id, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
                    [
                        'leave_type_id' => $type->id,
                        'days' => max(1, $service->workingDays($start, $end)),
                        'reason' => $reason,
                        'status' => $status,
                    ],
                );
            }
        }

        // Keep balances consistent with approved requests (idempotent recompute).
        LeaveBalance::where('year', $year)->get()->each(function (LeaveBalance $balance) use ($year) {
            $used = LeaveRequest::where('employee_id', $balance->employee_id)
                ->where('leave_type_id', $balance->leave_type_id)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->sum('days');

            $balance->update(['used' => $used]);
        });
    }
}
