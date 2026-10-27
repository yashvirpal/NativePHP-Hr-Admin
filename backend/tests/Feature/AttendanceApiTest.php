<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class AttendanceApiTest extends ApiTestCase
{
    public function test_punch_in_and_out_computes_worked_minutes(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-in')->assertCreated()->assertJsonPath('record.status', 'present');

        Carbon::setTestNow('2026-11-02 12:55:00');
        $this->postJson('/api/v1/attendance/punch-out')->assertOk()->assertJsonPath('record.worked_minutes', 240);

        $this->getJson('/api/v1/attendance/today')->assertOk()->assertJsonPath('is_punched_in', false);
    }

    public function test_second_punch_in_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();
        $this->postJson('/api/v1/attendance/punch-in')->assertUnprocessable()->assertJsonValidationErrors('punch');
    }

    public function test_punch_out_without_punch_in_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-out')->assertUnprocessable();
    }

    public function test_late_arrival_is_flagged_after_grace(): void
    {
        Sanctum::actingAs($this->makeUser());

        Carbon::setTestNow('2026-11-02 09:20:00'); // grace is 15 min
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated()->assertJsonPath('record.status', 'late');
    }

    public function test_within_grace_is_not_late(): void
    {
        Sanctum::actingAs($this->makeUser());

        Carbon::setTestNow('2026-11-02 09:10:00');
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated()->assertJsonPath('record.status', 'present');
    }

    public function test_client_cannot_spoof_source_or_time(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-in', ['source' => 'manual', 'punched_at' => '2020-01-01 00:00:00'])
            ->assertCreated()
            ->assertJsonPath('record.punches.0.source', 'mobile');

        $this->assertDatabaseHas('attendance_punches', ['punched_at' => '2026-11-02 08:55:00']);
    }

    public function test_user_without_employee_profile_is_forbidden(): void
    {
        Sanctum::actingAs(\App\Models\User::factory()->create());

        $this->postJson('/api/v1/attendance/punch-in')->assertForbidden();
    }

    public function test_attendance_month_list_surfaces_overtime_minutes_and_amount(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200]);

        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();
        Carbon::setTestNow('2026-11-02 12:55:00');
        $this->postJson('/api/v1/attendance/punch-out')->assertOk();

        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 240])->assertCreated();

        $this->getJson('/api/v1/attendance?month=2026-11')
            ->assertOk()
            ->assertJsonFragment(['work_date' => '2026-11-02', 'overtime_minutes' => 240, 'overtime_amount' => '800.00']);
    }

    public function test_manual_regular_hours_entry_is_created(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 120, 'reason' => 'Forgot to punch'])
            ->assertCreated()
            ->assertJsonPath('minutes', 120);

        $this->assertDatabaseHas('manual_regular_entries', [
            'employee_id' => $user->employee->id,
            'work_date' => '2026-11-02',
            'minutes' => 120,
            'reason' => 'Forgot to punch',
        ]);
    }

    public function test_manual_regular_hours_rejects_minutes_outside_1_to_1440(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 0])->assertUnprocessable();
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 1441])->assertUnprocessable();
    }

    public function test_manual_regular_hours_requires_work_date(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/manual', ['minutes' => 60])->assertUnprocessable()->assertJsonValidationErrors('work_date');
    }

    public function test_logging_manual_regular_hours_for_the_same_day_again_updates_it_in_place(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 60, 'reason' => 'First'])->assertCreated();
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 90, 'reason' => 'Corrected'])->assertCreated();

        $this->assertSame(1, \App\Models\ManualRegularEntry::where('employee_id', $user->employee->id)->where('work_date', '2026-11-02')->count());
        $this->assertDatabaseHas('manual_regular_entries', [
            'employee_id' => $user->employee->id, 'work_date' => '2026-11-02', 'minutes' => 90, 'reason' => 'Corrected',
        ]);
    }

    public function test_submitting_overtime_for_the_same_day_again_updates_it_in_place(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200]);

        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 120])->assertCreated()
            ->assertJsonPath('amount', '400.00');

        $this->assertSame(1, \App\Models\OvertimeRequest::where('employee_id', $user->employee->id)->where('work_date', '2026-11-02')->count());
    }

    public function test_day_entry_returns_zeros_when_nothing_was_logged_for_that_date(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/attendance/manual?work_date=2026-11-02')->assertOk()
            ->assertExactJson([
                'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null,
                'on_leave' => false, 'leave_type' => null,
            ]);
    }

    public function test_day_entry_reports_an_approved_leave_day(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $type = $this->leaveType(10);

        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ])->assertCreated();

        $this->getJson('/api/v1/attendance/manual?work_date=2026-11-02')->assertOk()
            ->assertJsonPath('on_leave', true)
            ->assertJsonPath('leave_type', 'Annual');

        // The day after the range is untouched.
        $this->getJson('/api/v1/attendance/manual?work_date=2026-11-04')->assertOk()
            ->assertJsonPath('on_leave', false);
    }

    public function test_calendar_marks_an_approved_leave_day_with_the_leave_type_name(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $type = $this->leaveType(10);

        // Mon 2nd (today) - Fri 6th.
        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-06',
        ])->assertCreated();

        $day = collect($this->getJson('/api/v1/attendance/calendar?month=2026-11')->assertOk()->json())
            ->firstWhere('work_date', '2026-11-02');

        $this->assertSame('leave', $day['status']);
        $this->assertSame('Annual', $day['leave_type']);
    }

    public function test_calendar_marks_a_day_with_manual_or_overtime_hours_but_no_punch_as_logged(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-01', 'minutes' => 90])->assertCreated();

        $days = collect($this->getJson('/api/v1/attendance/calendar?month=2026-11')->assertOk()->json())->keyBy('work_date');

        $this->assertSame('logged', $days['2026-11-01']['status']);
        $this->assertSame(90, $days['2026-11-01']['regular_minutes']);
        $this->assertNull($days['2026-11-01']['first_in_at']);
    }

    public function test_calendar_marks_a_past_day_with_nothing_recorded_as_absent(): void
    {
        Sanctum::actingAs($this->makeUser());

        $days = collect($this->getJson('/api/v1/attendance/calendar?month=2026-11')->assertOk()->json())->keyBy('work_date');

        $this->assertSame('absent', $days['2026-11-01']['status']); // yesterday, nothing logged
    }

    public function test_calendar_leaves_today_and_future_days_unmarked(): void
    {
        Sanctum::actingAs($this->makeUser());

        $days = collect($this->getJson('/api/v1/attendance/calendar?month=2026-11')->assertOk()->json())->keyBy('work_date');

        $this->assertSame('', $days['2026-11-02']['status']); // today
        $this->assertSame('', $days['2026-11-03']['status']); // future
    }

    public function test_calendar_keeps_punch_derived_status_and_combines_regular_minutes(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();
        Carbon::setTestNow('2026-11-02 12:55:00');
        $this->postJson('/api/v1/attendance/punch-out')->assertOk(); // 240 worked minutes
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 30])->assertCreated();

        $day = collect($this->getJson('/api/v1/attendance/calendar?month=2026-11')->assertOk()->json())
            ->firstWhere('work_date', '2026-11-02');

        $this->assertSame('present', $day['status']);
        $this->assertSame(210, $day['regular_minutes']); // (240 - 60) + 30
        $this->assertSame(60, $day['overtime_minutes']);
    }

    public function test_calendar_requires_the_month_parameter(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/attendance/calendar')->assertUnprocessable();
    }

    public function test_day_entry_returns_the_current_regular_and_overtime_values_for_that_date(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150]);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 90])->assertCreated();
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();

        $this->getJson('/api/v1/attendance/manual?work_date=2026-11-02')->assertOk()
            ->assertExactJson([
                'regular_minutes' => 90, 'overtime_minutes' => 60, 'overtime_amount' => '150.00',
                'on_leave' => false, 'leave_type' => null,
            ]);

        // A different date is untouched.
        $this->getJson('/api/v1/attendance/manual?work_date=2026-11-03')->assertOk()
            ->assertExactJson([
                'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null,
                'on_leave' => false, 'leave_type' => null,
            ]);
    }

    public function test_attendance_totals_combines_punch_derived_and_manual_regular_with_overtime(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200]);

        // 2026-11-02: 4h punched (240 min), 1h overtime -> 180 min punch-derived regular.
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();
        Carbon::setTestNow('2026-11-02 12:55:00');
        $this->postJson('/api/v1/attendance/punch-out')->assertOk();
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();

        // An earlier day in the same month: 2h logged manually, no punch at all.
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-01', 'minutes' => 120])->assertCreated();

        $this->getJson('/api/v1/attendance/totals?month=2026-11')
            ->assertOk()
            ->assertJsonPath('month.regular_minutes', 300) // (240-60) + 120
            ->assertJsonPath('month.overtime_minutes', 60)
            ->assertJsonPath('month.overtime_amount', '200.00');
    }

    public function test_attendance_totals_reports_leave_days_taken_for_month_and_year(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $type = $this->leaveType(10);

        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ])->assertCreated();

        Carbon::setTestNow('2026-05-11 08:55:00');
        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-05-11', 'end_date' => '2026-05-11',
        ])->assertCreated();

        Carbon::setTestNow('2026-11-02 08:55:00');
        $this->getJson('/api/v1/attendance/totals?month=2026-11')
            ->assertOk()
            ->assertJsonPath('month.leave_days', '2.0') // only November's leave
            ->assertJsonPath('year.leave_days', '3.0'); // November + May
    }

    public function test_attendance_totals_clamps_a_day_where_overtime_exceeds_worked_minutes_to_zero(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200]);

        // Only 30 minutes punched, but 60 minutes of overtime logged for the same day.
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();
        Carbon::setTestNow('2026-11-02 09:25:00');
        $this->postJson('/api/v1/attendance/punch-out')->assertOk();
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();

        $this->getJson('/api/v1/attendance/totals?month=2026-11')
            ->assertOk()
            ->assertJsonPath('month.regular_minutes', 0)
            ->assertJsonPath('month.overtime_minutes', 60);
    }

    public function test_attendance_totals_year_aggregates_across_months(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();

        Carbon::setTestNow('2026-05-10 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-05-10', 'minutes' => 90])->assertCreated();

        Carbon::setTestNow('2026-11-02 08:55:00');
        $response = $this->getJson('/api/v1/attendance/totals?month=2026-11')->assertOk();

        $response->assertJsonPath('month.regular_minutes', 60); // only November's entry
        $response->assertJsonPath('year.regular_minutes', 150); // both May + November
    }

    public function test_attendance_totals_defaults_to_current_month_when_month_param_omitted(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 45])->assertCreated();

        $this->getJson('/api/v1/attendance/totals')->assertOk()->assertJsonPath('month.regular_minutes', 45);
    }
}
