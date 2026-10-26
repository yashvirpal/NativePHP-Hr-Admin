<?php

namespace App\Enums;

/**
 * What an admin can be allowed to do. Roles (App\Models\Role) hold a list of these values.
 *
 * Deliberately NOT here: managing roles themselves, which only super admins may do (see AppServiceProvider),
 * so nobody can grant themselves power by editing a role.
 */
enum Permission: string
{
    case EmployeesView = 'employees.view';
    case EmployeesManage = 'employees.manage';
    case DepartmentsManage = 'departments.manage';
    case AttendanceView = 'attendance.view';
    case LeaveManage = 'leave.manage';
    case OvertimeManage = 'overtime.manage';
    case HolidaysManage = 'holidays.manage';
    case AdminsManage = 'admins.manage';

    public function label(): string
    {
        return match ($this) {
            self::EmployeesView => 'View employees',
            self::EmployeesManage => 'Create and edit employees',
            self::DepartmentsManage => 'Manage departments',
            self::AttendanceView => 'View attendance',
            self::LeaveManage => 'View leave requests',
            self::OvertimeManage => 'View overtime requests',
            self::HolidaysManage => 'Manage holidays',
            self::AdminsManage => 'Manage admin accounts',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::EmployeesView, self::EmployeesManage, self::DepartmentsManage => 'People',
            self::AttendanceView, self::LeaveManage, self::OvertimeManage, self::HolidaysManage => 'Time',
            self::AdminsManage => 'Administration',
        };
    }

    /** @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
