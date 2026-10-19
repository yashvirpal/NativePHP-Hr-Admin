<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Annual Leave', 'AL', 18, true],
            ['Sick Leave', 'SL', 10, true],
            ['Casual Leave', 'CL', 6, true],
            ['Unpaid Leave', 'UL', 0, false],
            ['Maternity Leave', 'ML', 90, true],
            ['Paternity Leave', 'PL', 10, true],
            ['Bereavement Leave', 'BL', 5, true],
            ['Compensatory Off', 'CO', 4, true],
            ['Study Leave', 'STL', 5, false],
            ['Marriage Leave', 'MAR', 5, true],
        ] as [$name, $code, $days, $paid]) {
            LeaveType::firstOrCreate(['code' => $code], ['name' => $name, 'days_per_year' => $days, 'is_paid' => $paid]);
        }
    }
}
