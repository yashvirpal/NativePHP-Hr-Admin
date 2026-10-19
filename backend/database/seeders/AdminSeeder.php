<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Back-office accounts (admin guard), one per kind of role. Password for all: "password". */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $roles = Role::pluck('id', 'slug');

        foreach ([
            ['Admin User', 'admin@example.com', 'super_admin'],
            ['Owner', 'owner@example.com', 'super_admin'],
            ['HR Manager', 'manager@example.com', 'manager'],
            ['Priya Sharma', 'priya.manager@example.com', 'hr_manager'],
            ['Rajesh Kumar', 'rajesh.manager@example.com', 'hr_executive'],
            ['Sunita Rao', 'sunita.manager@example.com', 'payroll_officer'],
            ['Deepak Nair', 'deepak.manager@example.com', 'attendance_clerk'],
            ['Lakshmi Iyer', 'lakshmi.manager@example.com', 'leave_approver'],
            ['Farhan Ali', 'farhan.manager@example.com', 'overtime_approver'],
            ['Gita Menon', 'gita.manager@example.com', 'auditor'],
        ] as [$name, $email, $slug]) {
            $admin = Admin::firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => $password, 'role_id' => $roles[$slug],
            ]);

            // Existing dev databases only had super_admin/manager; move seeded accounts onto their intended role.
            if ($admin->role_id !== $roles[$slug]) {
                $admin->update(['role_id' => $roles[$slug]]);
            }
        }
    }
}
