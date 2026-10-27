<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class EarningsApiTest extends ApiTestCase
{
    public function test_earnings_monthly_window_covers_only_the_current_month(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200]);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 120])->assertCreated();
        $this->postJson('/api/v1/overtime-requests', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-10-15', 'minutes' => 90])->assertCreated();

        $this->getJson('/api/v1/earnings?months=1')->assertOk()
            ->assertJsonPath('months', 1)
            ->assertJsonPath('regular_minutes', 120) // October's entry is outside a 1-month window
            ->assertJsonPath('overtime_minutes', 60)
            ->assertJsonPath('overtime_amount', '200.00');
    }

    public function test_earnings_3_month_window_aggregates_the_trailing_three_months(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated(); // Nov: in

        Carbon::setTestNow('2026-10-10 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-10-10', 'minutes' => 60])->assertCreated(); // Oct: in

        Carbon::setTestNow('2026-09-05 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-09-05', 'minutes' => 60])->assertCreated(); // Sep: in (edge)

        Carbon::setTestNow('2026-08-20 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-08-20', 'minutes' => 60])->assertCreated(); // Aug: out

        Carbon::setTestNow('2026-11-02 08:55:00');
        $this->getJson('/api/v1/earnings?months=3')->assertOk()
            ->assertJsonPath('from', '2026-09-01')
            ->assertJsonPath('to', '2026-11-30')
            ->assertJsonPath('regular_minutes', 180); // Sep + Oct + Nov, not Aug
    }

    public function test_earnings_6_month_window_aggregates_the_trailing_six_months(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated(); // in

        Carbon::setTestNow('2026-06-15 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-06-15', 'minutes' => 60])->assertCreated(); // in (edge)

        Carbon::setTestNow('2026-05-15 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-05-15', 'minutes' => 60])->assertCreated(); // out

        Carbon::setTestNow('2026-11-02 08:55:00');
        $this->getJson('/api/v1/earnings?months=6')->assertOk()
            ->assertJsonPath('from', '2026-06-01')
            ->assertJsonPath('regular_minutes', 120); // June + Nov, not May
    }

    public function test_earnings_12_month_window_aggregates_the_trailing_year(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated(); // in

        Carbon::setTestNow('2025-12-10 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2025-12-10', 'minutes' => 60])->assertCreated(); // in (edge)

        Carbon::setTestNow('2025-11-20 08:55:00');
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2025-11-20', 'minutes' => 60])->assertCreated(); // out

        Carbon::setTestNow('2026-11-02 08:55:00');
        $this->getJson('/api/v1/earnings?months=12')->assertOk()
            ->assertJsonPath('from', '2025-12-01')
            ->assertJsonPath('regular_minutes', 120); // Dec 2025 + Nov 2026, not Nov 2025
    }

    public function test_earnings_includes_leave_days_taken_in_the_window(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $type = LeaveType::create(['name' => 'Annual Leave', 'code' => 'AL', 'days_per_year' => 18]);

        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ])->assertCreated()->assertJsonPath('days', '2.0');

        $this->getJson('/api/v1/earnings?months=1')->assertOk()->assertJsonPath('leave_days', '2.0');
    }

    public function test_earnings_excludes_cancelled_leave(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $type = LeaveType::create(['name' => 'Annual Leave', 'code' => 'AL', 'days_per_year' => 18]);

        $id = $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02',
        ])->assertCreated()->json('id');
        $this->postJson("/api/v1/leave-requests/{$id}/cancel")->assertOk();

        $this->getJson('/api/v1/earnings?months=1')->assertOk()->assertJsonPath('leave_days', '0.0');
    }

    public function test_earnings_custom_range_overrides_months(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-08-15', 'minutes' => 60])->assertCreated();
        $this->postJson('/api/v1/attendance/manual', ['work_date' => '2026-11-02', 'minutes' => 60])->assertCreated();

        $this->getJson('/api/v1/earnings?from=2026-08-01&to=2026-08-31&months=1')->assertOk()
            ->assertJsonPath('months', null)
            ->assertJsonPath('from', '2026-08-01')
            ->assertJsonPath('to', '2026-08-31')
            ->assertJsonPath('regular_minutes', 60); // only August's entry, not November's
    }

    public function test_earnings_custom_range_includes_leave_days_in_that_window(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $type = LeaveType::create(['name' => 'Annual Leave', 'code' => 'AL', 'days_per_year' => 18]);

        // Leave requests can't be backdated, so this one sits on "today" itself.
        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02',
        ])->assertCreated();

        $this->getJson('/api/v1/earnings?from=2026-11-01&to=2026-11-02')->assertOk()
            ->assertJsonPath('leave_days', '1.0');
    }

    public function test_earnings_custom_range_requires_to_after_from(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/earnings?from=2026-08-10&to=2026-08-01')->assertUnprocessable();
    }

    public function test_earnings_custom_range_cannot_end_in_the_future(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/earnings?from=2026-11-01&to=2026-11-30')->assertUnprocessable();
    }

    public function test_earnings_custom_range_requires_both_from_and_to(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/earnings?from=2026-08-01')->assertUnprocessable();
    }

    public function test_earnings_rejects_an_invalid_months_value(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/earnings?months=2')->assertUnprocessable();
    }

    public function test_earnings_requires_months_param(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/earnings')->assertUnprocessable();
    }
}
