<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class EmployeePortalTest extends ApiTestCase
{
    public function test_guests_are_redirected_to_employee_login(): void
    {
        $this->get('/employee')->assertRedirect('/employee/login');
        $this->get('/')->assertRedirect('/employee');
    }

    public function test_employee_logs_in_with_the_default_guard(): void
    {
        $user = $this->makeUser();

        $this->post('/employee/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/employee');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('admin');
    }

    public function test_employee_can_self_register_and_is_signed_in(): void
    {
        $this->post('/employee/register', [
            'name' => 'Jamie Employee', 'email' => 'jamie@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/employee');

        $user = User::where('email', 'jamie@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');

        $employee = $user->employee;
        $this->assertSame('active', $employee->status);
        $this->assertNull($employee->department_id);
        $this->assertNull($employee->shift_id);
    }

    public function test_registration_requires_a_unique_email(): void
    {
        $existing = $this->makeUser();

        $this->post('/employee/register', [
            'name' => 'Someone Else', 'email' => $existing->email,
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_credentials_cannot_sign_in_to_the_employee_portal(): void
    {
        $admin = $this->makeAdmin();

        $this->post('/employee/login', ['email' => $admin->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_inactive_employee_is_signed_out_immediately(): void
    {
        $user = $this->makeUser(['status' => 'inactive']);

        $this->post('/employee/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_an_admin_session_cannot_open_employee_pages(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')->get('/employee')->assertRedirect('/employee/login');
    }

    public function test_employee_sees_their_dashboard_and_can_punch_in_and_out(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->get('/employee')->assertOk()->assertSee($user->name)->assertSee('Not punched in');

        $this->post('/employee/punch-in')->assertRedirect();
        $this->get('/employee')->assertSee('Punched in since 08:55');

        Carbon::setTestNow('2026-11-02 17:00:00');
        $this->post('/employee/punch-out')->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $user->employee->id, 'worked_minutes' => 485, 'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_punches', ['source' => 'web', 'type' => 'in']);
    }

    public function test_dashboard_has_no_punch_buttons_and_shows_this_months_regular_and_overtime(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->put('/employee/overtime/rate', ['hourly_rate' => '200']);
        $this->post('/employee/overtime', ['work_date' => '2026-11-02', 'minutes' => 60]); // 200.00

        $response = $this->get('/employee')->assertOk();

        $response->assertDontSee('Punch in')->assertDontSee('Punch out');
        $response->assertSee('Overtime (this month)')->assertSee('1h 0m')->assertSee('200.00');
    }

    public function test_dashboard_shows_leaves_this_month_instead_of_late_arrivals(): void
    {
        $user = $this->makeUser();
        LeaveType::create(['name' => 'Annual Leave', 'code' => 'AL', 'days_per_year' => 18]);
        $this->actingAs($user, 'web');

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/leave-requests', [
            'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ])->assertCreated();

        $response = $this->actingAs($user, 'web')->get('/employee')->assertOk();

        $response->assertDontSee('Late arrivals');
        $response->assertSee('Leaves (this month)')->assertSee('2.0');
    }

    public function test_dashboard_shows_this_years_totals_separately_from_this_month(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->put('/employee/overtime/rate', ['hourly_rate' => '200']);
        $this->post('/employee/overtime', ['work_date' => '2026-11-02', 'minutes' => 60]); // this month: 200.00
        $this->post('/employee/overtime', ['work_date' => '2026-05-10', 'minutes' => 60]); // earlier this year: 200.00

        $response = $this->get('/employee')->assertOk();

        $response->assertSee('Overtime (this year)')->assertSee('2h 0m')->assertSee('400.00');
    }

    public function test_dashboard_shows_lifetime_overtime_total(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200]);
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-10-01', 'minutes' => 60])->assertCreated();
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-01', 'minutes' => 30])->assertCreated();

        $response = $this->actingAs($user, 'web')->get('/employee')->assertOk();

        $response->assertSee('Overtime total (all-time)')->assertSee('300.00'); // (1h + 0.5h) at 200.00/h
    }

    public function test_double_punch_in_shows_an_error(): void
    {
        $this->actingAs($this->makeUser(), 'web');

        $this->post('/employee/punch-in');
        $this->post('/employee/punch-in')->assertSessionHasErrors('punch');
    }

    public function test_employee_applies_for_and_cancels_leave_on_the_web(): void
    {
        $user = $this->makeUser();
        $type = $this->leaveType(10);
        $this->actingAs($user, 'web');

        $this->post('/employee/leave', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-03', 'end_date' => '2026-11-04', 'reason' => 'Trip',
        ])->assertRedirect('/employee/leave');

        $leave = LeaveRequest::where('employee_id', $user->employee->id)->firstOrFail();
        $this->assertSame('2.0', $leave->days);
        $this->get('/employee/leave')->assertOk()->assertSee('Trip');

        $this->post("/employee/leave/{$leave->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $leave->fresh()->status);
    }

    public function test_employee_cannot_cancel_another_employees_leave(): void
    {
        $owner = $this->makeUser();
        $leave = LeaveRequest::create([
            'employee_id' => $owner->employee->id, 'leave_type_id' => $this->leaveType()->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'days' => 1, 'status' => 'approved',
        ]);

        $this->actingAs($this->makeUser(), 'web')->post("/employee/leave/{$leave->id}/cancel")->assertForbidden();
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_employee_manages_their_companies(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->post('/employee/companies', ['name' => 'Acme Ltd', 'is_current' => '1'])->assertRedirect('/employee/companies');
        $company = Company::where('employee_id', $user->employee->id)->firstOrFail();
        $this->assertTrue($company->is_current);

        $this->get('/employee/companies')->assertOk()->assertSee('Acme Ltd');
        $this->get("/employee/companies/{$company->id}/edit")->assertOk();

        // Unticking "I currently work here" (checkbox absent) demands the period worked there.
        $this->from("/employee/companies/{$company->id}/edit")
            ->put("/employee/companies/{$company->id}", ['name' => 'Acme Corp'])
            ->assertRedirect("/employee/companies/{$company->id}/edit")
            ->assertSessionHasErrors(['started_on', 'ended_on']);
        $this->assertTrue($company->fresh()->is_current, 'rejected update must not change the record');

        $this->put("/employee/companies/{$company->id}", [
            'name' => 'Acme Corp', 'started_on' => '2021-03-01', 'ended_on' => '2023-09-15',
        ])->assertRedirect('/employee/companies');

        $company->refresh();
        $this->assertSame('Acme Corp', $company->name);
        $this->assertFalse($company->is_current, 'unticked checkbox means not current');
        $this->assertSame('2023-09-15', $company->ended_on->toDateString());
        $this->get('/employee/companies')->assertSee('Mar 2021')->assertSee('Sep 2023');

        $this->delete("/employee/companies/{$company->id}")->assertRedirect('/employee/companies');
        $this->assertModelMissing($company);
    }

    public function test_employee_cannot_edit_or_delete_another_employees_company(): void
    {
        $other = Company::create(['employee_id' => $this->makeUser()->employee->id, 'name' => 'Not Mine']);
        $this->actingAs($this->makeUser(), 'web');

        $this->get("/employee/companies/{$other->id}/edit")->assertForbidden();
        $this->put("/employee/companies/{$other->id}", ['name' => 'X'])->assertForbidden();
        $this->delete("/employee/companies/{$other->id}")->assertForbidden();
        $this->assertSame('Not Mine', $other->fresh()->name);
    }

    public function test_employee_pages_render_with_full_seed_data(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'alice@example.com')->firstOrFail(), 'web');

        foreach (['/employee', '/employee/attendance', '/employee/attendance?month=2026-10', '/employee/leave',
            '/employee/overtime', '/employee/holidays', '/employee/holidays?year=2025', '/employee/companies',
            '/employee/companies/create'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_both_panels_can_be_signed_in_at_once_and_log_out_independently(): void
    {
        $employee = $this->makeUser();
        $admin = $this->makeAdmin();

        $this->post('/employee/login', ['email' => $employee->email, 'password' => 'password']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($employee, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');

        $this->post('/admin/logout');
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($employee, 'web');
    }

    public function test_api_and_web_share_the_same_data(): void
    {
        $user = $this->makeUser();

        // Punch in through the mobile API...
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();

        // ...and the web portal (same service, same database) sees it.
        $this->actingAs($user, 'web')->get('/employee')->assertSee('Punched in since 08:55');
        $this->assertSame(1, Admin::count() + 1, 'sanity: admins table untouched by employees');
    }
}
