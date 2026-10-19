<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OvertimeRate;
use App\Models\OvertimeRequest;
use App\Services\OvertimeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OvertimeSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = ['Production release', 'Client deadline', 'Month-end closing', 'Incident support', 'Inventory audit'];

        Employee::orderBy('id')->take(16)->get()->each(function (Employee $employee, int $i) use ($reasons) {
            $date = Carbon::today()->subDays(2 + $i);
            while ($date->isWeekend()) {
                $date->subDay();
            }

            $minutes = 30 * (1 + $i % 8);

            // Price it with the rate that was in force on the day it was worked (a snapshot, like real
            // submissions: later raises never change it).
            $rate = OvertimeRate::where('employee_id', $employee->id)
                ->where('effective_from', '<=', $date->copy()->endOfDay())
                ->orderByDesc('effective_from')->orderByDesc('id')
                ->value('hourly_rate');

            $request = OvertimeRequest::firstOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $date->toDateString()],
                [
                    'minutes' => $minutes,
                    'reason' => $reasons[$i % count($reasons)],
                    'status' => ['pending', 'approved', 'approved', 'rejected'][$i % 4],
                    'rate_per_hour' => $rate,
                    'amount' => $rate === null ? null : OvertimeService::amount($minutes, $rate),
                ],
            );

            // Requests seeded before rates existed get their snapshot once; real, already-priced rows are left alone.
            if ($request->rate_per_hour === null && $rate !== null) {
                $request->update(['rate_per_hour' => $rate, 'amount' => OvertimeService::amount($request->minutes, $rate)]);
            }
        });
    }
}
