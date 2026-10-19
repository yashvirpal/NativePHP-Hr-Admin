<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development data (idempotent — safe to run repeatedly). All seeded accounts use the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            ShiftSeeder::class,
            LeaveTypeSeeder::class,
            HolidaySeeder::class,
            RoleSeeder::class,
            AdminSeeder::class,
            UserEmployeeSeeder::class,
            CompanySeeder::class,
            OvertimeRateSeeder::class,
            LeaveSeeder::class,
            AttendanceSeeder::class,
            OvertimeSeeder::class,
            ApprovalSeeder::class,
        ]);
    }
}
