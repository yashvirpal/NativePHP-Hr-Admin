<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OvertimeRate;
use Illuminate\Database\Seeder;

/**
 * Hourly overtime rates. Everyone starts with a rate; every third employee later got a raise,
 * so there is real history (the OvertimeSeeder prices old requests with the OLD rate).
 */
class OvertimeRateSeeder extends Seeder
{
    public function run(): void
    {
        Employee::orderBy('id')->get()->each(function (Employee $employee, int $i) {
            $base = 150 + ($i % 7) * 25; // 150 .. 300

            OvertimeRate::firstOrCreate(
                ['employee_id' => $employee->id, 'effective_from' => now()->subMonths(6)->startOfDay()],
                ['hourly_rate' => number_format($base, 2, '.', '')],
            );

            if ($i % 3 === 0) {
                OvertimeRate::firstOrCreate(
                    ['employee_id' => $employee->id, 'effective_from' => now()->subDays(8)->startOfDay()],
                    ['hourly_rate' => number_format($base + 50, 2, '.', '')],
                );
            }
        });
    }
}
