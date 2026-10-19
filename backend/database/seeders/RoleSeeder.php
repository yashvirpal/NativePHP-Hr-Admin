<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Ten sample roles. `super_admin` and `manager` already exist from the migration (the seeder only creates
 * what is missing, so edits made in the UI are never overwritten).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $p = fn (Permission ...$ps) => array_map(fn (Permission $x) => $x->value, $ps);

        foreach ([
            ['Super admin', 'Full access, including roles and admin accounts.', Permission::values(), true],
            ['Manager', 'Day-to-day HR operations.', $p(
                Permission::EmployeesView, Permission::EmployeesManage, Permission::DepartmentsManage,
                Permission::AttendanceView, Permission::LeaveManage, Permission::OvertimeManage, Permission::HolidaysManage,
            ), false],
            ['HR Manager', 'People, leave and holidays; can also manage admin accounts below their own access.', $p(
                Permission::EmployeesView, Permission::EmployeesManage, Permission::DepartmentsManage,
                Permission::AttendanceView, Permission::LeaveManage, Permission::HolidaysManage, Permission::AdminsManage,
            ), false],
            ['HR Executive', 'Maintains employee records and handles leave.', $p(
                Permission::EmployeesView, Permission::EmployeesManage, Permission::AttendanceView, Permission::LeaveManage,
            ), false],
            ['Payroll Officer', 'Reads attendance and approves overtime for payroll.', $p(
                Permission::EmployeesView, Permission::AttendanceView, Permission::OvertimeManage,
            ), false],
            ['Attendance Clerk', 'Monitors daily attendance.', $p(Permission::EmployeesView, Permission::AttendanceView), false],
            ['Leave Approver', 'Decides leave requests only.', $p(Permission::EmployeesView, Permission::LeaveManage), false],
            ['Overtime Approver', 'Decides overtime requests only.', $p(Permission::AttendanceView, Permission::OvertimeManage), false],
            ['Department Head', 'Approves leave and overtime for the team and watches attendance.', $p(
                Permission::EmployeesView, Permission::AttendanceView, Permission::LeaveManage, Permission::OvertimeManage,
            ), false],
            ['Auditor', 'Read-only access to people and attendance.', $p(Permission::EmployeesView, Permission::AttendanceView), false],
        ] as [$name, $description, $permissions, $system]) {
            $slug = \Illuminate\Support\Str::slug($name, '_');

            Role::firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'is_system' => $slug === Role::SUPER_ADMIN,
            ]);
        }
    }
}
