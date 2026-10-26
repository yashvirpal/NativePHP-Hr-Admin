<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Approval;
use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;

class AdminDashboardTest extends ApiTestCase
{
    public function test_guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/employees')->assertRedirect('/admin/login');
    }

    public function test_admin_logs_in_with_admin_credentials(): void
    {
        $admin = $this->makeAdmin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
    }

    public function test_employee_credentials_cannot_sign_in_to_the_admin_panel(): void
    {
        $user = $this->makeUser();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertGuest('web');
    }

    public function test_an_employee_session_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->makeUser(), 'web')->get('/admin/employees')->assertRedirect('/admin/login');
    }

    public function test_the_default_manager_role_covers_operations_but_not_administration(): void
    {
        $this->actingAs($this->makeAdmin('manager'), 'admin');

        foreach (['/admin', '/admin/employees', '/admin/employees/create', '/admin/departments', '/admin/holidays',
            '/admin/attendance', '/admin/leave-requests', '/admin/overtime-requests'] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
    }

    public function test_super_admin_opens_every_page(): void
    {
        $this->seed();
        $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

        foreach (['/admin', '/admin/employees', '/admin/employees?page=2', '/admin/employees/3/edit', '/admin/departments',
            '/admin/holidays', '/admin/attendance', '/admin/attendance?date=2026-10-28', '/admin/leave-requests',
            '/admin/leave-requests?status=all', '/admin/overtime-requests', '/admin/overtime-requests?status=all',
            '/admin/admins', '/admin/admins/create', '/admin/admins/1/edit', '/admin/roles', '/admin/roles/create',
            '/admin/roles/3/edit'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_admin_creates_an_employee_in_the_users_table(): void
    {
        $this->actingAs($this->makeAdmin('manager'), 'admin')->post('/admin/employees', [
            'name' => 'New Hire', 'email' => 'new@example.com', 'password' => 'password123',
            'employee_code' => 'N1', 'shift_id' => $this->shift->id, 'status' => 'active',
        ])->assertRedirect('/admin/employees');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('N1', $user->employee->employee_code);
        $this->assertDatabaseMissing('admins', ['email' => 'new@example.com']);
    }

    public function test_seeding_twice_creates_no_duplicates_and_meets_minimums(): void
    {
        $this->seed();
        $counts = fn () => [
            Role::count(), Admin::count(), User::count(), Company::count(), \App\Models\AttendanceRecord::count(),
            LeaveRequest::count(), Approval::count(), LeaveBalance::count(),
        ];
        $first = $counts();

        $this->seed();

        $this->assertSame($first, $counts());
        foreach ($first as $n) {
            $this->assertGreaterThanOrEqual(10, $n);
        }
    }
}
